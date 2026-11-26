<?php

namespace Coretik\Services\Forms\Tunnel;

use Coretik\Services\Forms\AsyncForm;
use Coretik\Services\Forms\Concerns\RespondsWithJson;
use Coretik\Services\Forms\Core\Exception;
use Coretik\Services\Forms\Core\Utils;
use Coretik\Services\Forms\Tunnel\Step\StepInterface;
use Illuminate\Support\Collection;

/**
 * Multi-step form answering JSON, driven by the forms scripts ([data-form-tunnel], scripts/TunnelForm.js).
 *
 * - tunnel-action=validate-step & step=n: validates the steps 1..n, answers { valid, values|errors, notices }
 * - tunnel-action=validate-tunnel: validates every step, runs run() then onSuccess()
 *
 *   class Registration extends TunnelForm {
 *       const ID = 'registration';
 *       protected function buildSteps(): void {
 *           $this->addStep(ContactStep::make($this))->addStep(SummaryStep::make($this));
 *       }
 *       protected function isValidContext(): bool { return true; }
 *       protected function run(): void { $values = $this->getValues(); ... }
 *   }
 *
 * Template: templates/forms/{TEMPLATE ?? ID}.php, else templates/forms/tunnel-form.php, else the Coretik default view.
 */
abstract class TunnelForm extends AsyncForm implements TunnelFormInterface
{
    use RespondsWithJson;

    const ID = null;
    const TEMPLATE = null;
    const TEMPLATE_FALLBACK = 'tunnel-form';

    /** @var Collection<int, StepInterface> */
    protected Collection $steps;
    protected int $currentStep = 1;

    /**
     * Plain text message displayed in [data-form-success] after the final submission, when there is no successTpl.
     */
    protected ?string $successMessage = null;

    /**
     * HTML (built server side, never raw user input) injected in [data-form-success] after the final submission.
     */
    protected ?string $successTpl = null;

    /**
     * Adds the steps: $this->addStep(FirstStep::make($this))->addStep(...)
     */
    abstract protected function buildSteps(): void;

    public function __construct()
    {
        if (empty(static::ID)) {
            throw new \LogicException(\sprintf('%s: no form id, define the ID constant.', static::class));
        }

        $this->steps = new Collection();
        $this->buildSteps();
        parent::__construct(static::ID, [], static::TEMPLATE ?? static::ID);
    }

    /**
     * Core\Handler clones the form registered as factory for each get(): the steps are built again from a blank state.
     */
    public function __clone()
    {
        $this->currentStep = 1;
        $this->uniqid = null;
        $this->successTpl = null;
        $this->steps = new Collection();
        $this->buildSteps();
        // The constraints (callbacks) may reference the steps: built again with the new ones
        $this->loadFields();
    }

    public function getRules(): array
    {
        $rules = [];
        foreach ($this->steps() as $step) {
            $rules = \array_merge($rules, $step->getRules());
        }
        return $rules;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function hasField(string $field): bool
    {
        return \array_key_exists($field, $this->fields);
    }

    public function fieldLabel($field): string
    {
        return !empty($this->fields[$field]['name'])
            ? $this->fields[$field]['name']
            : \ucfirst(\str_replace(['_', '-'], ' ', (string)$field));
    }

    /*
     * Steps
     */

    public function steps(): Collection
    {
        return $this->steps;
    }

    public function setSteps(array $steps): self
    {
        foreach ($steps as $step) {
            $this->addStep($step);
        }
        return $this;
    }

    public function addStep(StepInterface $step): self
    {
        $this->steps->push($step);
        return $this;
    }

    /**
     * Adds a step built from the last one: fn (StepInterface $last, Collection $steps) => StepInterface
     */
    public function then(callable $callback): self
    {
        $this->addStep($callback($this->steps->last(), $this->steps));
        return $this;
    }

    /**
     * @template T of StepInterface
     * @param class-string<T>|T $step
     * @return T|null
     */
    public function getStep(string|StepInterface $step): ?StepInterface
    {
        if ($step instanceof StepInterface) {
            $step = $step::class;
        }

        return $this->steps->first(fn ($s) => $s::class === $step);
    }

    public function hasStep(string|StepInterface $stepClassOrName): bool
    {
        return null !== $this->getStep($stepClassOrName);
    }

    /**
     * @param int $index 0-based
     */
    public function getStepAt(int $index): ?StepInterface
    {
        return $index >= 0 ? $this->steps->get($index) : null;
    }

    public function getStepBefore(StepInterface $step): ?StepInterface
    {
        $index = $this->steps->search($step, true);
        if ($index === false || $index === 0) {
            return null;
        }
        return $this->steps->get($index - 1);
    }

    public function getStepAfter(StepInterface $step): ?StepInterface
    {
        $index = $this->steps->search($step, true);
        if ($index === false || $index === $this->steps->count() - 1) {
            return null;
        }
        return $this->steps->get($index + 1);
    }

    /**
     * Categories of personal data collected by the steps (rule "rgpd" => ['category' => ...]).
     *
     * @return array<string, string[]> category => labels of the fields
     */
    public function getRgpdDataCategories(): array
    {
        $categories = [];
        foreach ($this->steps() as $step) {
            foreach ($step->getRgpdDataCategories() as $category => $data) {
                $categories[$category] = \array_merge($categories[$category] ?? [], $data);
            }
        }
        return $categories;
    }

    /*
     * View
     */

    /**
     * 1-based step displayed first.
     */
    public function setCurrentStep(int $step): self
    {
        $this->currentStep = \max(1, \min($step, $this->steps->count()));
        return $this;
    }

    public function successMessage(): string
    {
        return $this->successMessage ?? \__('Votre demande a bien été enregistrée.', 'coretik');
    }

    public function setSuccessMessage(?string $message): self
    {
        $this->successMessage = $message;
        return $this;
    }

    public function view($data = [], bool $return = false)
    {
        if (empty($this->config->locator()->locateTemplate($this->template))) {
            $this->template = static::TEMPLATE_FALLBACK;
        }

        return parent::view(
            $this->jsonViewData(\array_merge($data, [
                'steps' => $this->steps(),
                'currentStep' => $this->currentStep,
                'successMessage' => $this->successMessage(),
            ])),
            $return
        );
    }

    /*
     * Processing
     */

    public function isValidatingStep(): bool
    {
        if (!$this->isSubmitting()) {
            return false;
        }

        if (!isset($_POST['tunnel-action'], $_POST['step'])) {
            return false;
        }

        return 'validate-step' === Utils::sanitizeFormField($_POST['tunnel-action'])
            && (int)$_POST['step'] > 0;
    }

    public function isValidatingWholeForm(): bool
    {
        if (!$this->isSubmitting()) {
            return false;
        }

        return isset($_POST['tunnel-action'])
            && 'validate-tunnel' === Utils::sanitizeFormField($_POST['tunnel-action']);
    }

    /**
     * 1-based index of the step to validate.
     */
    protected function postedStepIndex(): int
    {
        return (int)($_POST['step'] ?? 0);
    }

    public function process()
    {
        if ($this->isSubmitting()) {
            // Persistent metas posted by the form (see AsyncForm::process())
            if (!empty($_REQUEST['persistent-data']) && \is_array($_REQUEST['persistent-data'])) {
                $this->persistentMetaKey = \array_merge(\array_keys($_REQUEST['persistent-data']), $this->persistentMetaKey);
            }
            $this->processPostedData();
        }

        if (Utils::isActionRefresh()) {
            return;
        }

        if (!$this->submittedOk()) {
            $this->onValidationError();
            $this->onError();
            return;
        }

        if ($this->isValidatingStep()) {
            $this->sendSuccess();
            return;
        }

        try {
            $this->run();
            $this->onSuccess();
            $this->sendSuccess($this->successTpl ? ['successTpl' => $this->successTpl] : []);
        } catch (Exception $e) {
            if (!empty($e->getMessage())) {
                $this->view_data['errors'][] = $e->getMessage();
            }
            $this->onProcessingError($e);
            $this->onError();
        }
    }

    public function processPostedData()
    {
        $result = ['ok' => false, 'error' => '', 'data' => [], 'validation_errors' => []];
        $form_name = $this->getFormName();

        if (!isset($_POST[$form_name]) || !\is_array($_POST[$form_name])) {
            $result['error'] = 'No form found';
            $this->submission_result = $result;
            return;
        }

        $posted_data = $_POST[$form_name];

        if ($this->isSpam()) {
            $result['error'] = 'Anti-robots spam validation failed';
            $this->submission_result = $result;
            $this->setDisplayErrors(false);
            return;
        }

        if (!$this->checkNonce()) {
            $result['error'] = 'Wrong / empty nonce';
            $this->submission_result = $result;
            $this->setDisplayErrors(false);
            return;
        }

        if (($posted_data['form_id'] ?? null) !== $this->id) {
            $result['error'] = 'Wrong form id';
            $this->submission_result = $result;
            return;
        }

        if ($this->isRefreshing()) {
            return;
        }

        if ($this->isValidatingStep()) {
            $stepIndex = $this->postedStepIndex();
            $step = $this->getStepAt($stepIndex - 1);

            if (!$step) {
                $result['error'] = 'No step found';
                $this->submission_result = $result;
                return;
            }

            if ($step->validate()->isValid()) {
                $result['ok'] = true;
                $result['data'] = $step->getValidation()?->getData() ?? [];
                \do_action('coretik/forms/tunnel/valid_step_submitted', $this->id, $stepIndex, $result['data'], $this);
            } else {
                $result['error'] = 'Validation failed';
                // Errors of the requested step and of the steps it depends on
                for ($i = 0; $i < $stepIndex; $i++) {
                    $previous = $this->getStepAt($i);
                    if ($previous && !$previous->isValid()) {
                        $result['validation_errors'] = \array_merge($result['validation_errors'], $previous->getErrors());
                    }
                }
            }
        } elseif ($this->isValidatingWholeForm()) {
            // Rate limit (coretik/forms/rate_limit filter): only on the final submission,
            // the step validations send several requests per submission
            if ($this->isRateLimited()) {
                $result['error'] = 'Too many submissions';
                $this->submission_result = $result;
                return;
            }

            if ($this->validateSteps()) {
                $result['ok'] = true;
                $result['data'] = $this->getValues();
                \do_action('coretik/forms/tunnel/valid_form_submitted', $this->id, $result['data'], $this);
            } else {
                $result['error'] = 'Validation failed';
                $result['validation_errors'] = $this->getStepsErrors();
            }
        } else {
            $result['error'] = 'No validation';
        }

        $this->submission_result = $result;
    }

    /**
     * Based on processPostedData(): nonce, anti-spam and form id are checked before any run().
     */
    public function submittedOk()
    {
        return $this->isSubmitting()
            && !empty($this->submission_result['ok'])
            && ($this->isValidatingStep() || $this->isValidatingWholeForm());
    }

    protected function validateSteps(): bool
    {
        $valid = true;
        foreach ($this->steps() as $step) {
            if (!$step->validate()->isValid()) {
                $valid = false;
            }
        }
        return $valid;
    }

    /**
     * Validated values in the JSON format of the scripts: [{ step, field, label, private, value }]
     */
    protected function getStepValues(): array
    {
        $data = [];
        foreach ($this->steps() as $step) {
            $data = \array_merge($data, $step->getValues());
        }
        return $data;
    }

    protected function getStepsErrors(): array
    {
        $errors = [];
        foreach ($this->steps() as $step) {
            if (!$step->isValid()) {
                $errors = \array_merge($errors, $step->getErrors());
            }
        }
        return $errors;
    }

    /**
     * Validated values of every step: [field => value]
     */
    public function getValues()
    {
        $values = [];
        foreach ($this->steps() as $step) {
            $values = \array_merge($values, $step->payload());
        }
        return $values;
    }

    /**
     * Validated values grouped by step, empty values excluded (summary email, export...).
     *
     * @return array<int, array{title: string, payload: array}>
     */
    public function payload(): array
    {
        return $this->steps()
            ->map(function (StepInterface $step) {
                $payload = \array_filter($step->payload(), fn ($value) => !\in_array($value, [null, '', []], true));
                if (empty($payload)) {
                    return null;
                }

                return [
                    'title' => $step->getTitle(),
                    'payload' => $payload,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Runs the onSuccess() hook of the steps. Override (calling parent) to notify, save...
     */
    protected function onSuccess()
    {
        foreach ($this->steps() as $step) {
            if (\method_exists($step, 'onSuccess')) {
                $step->onSuccess();
            }
        }
    }

    protected function onError()
    {
        foreach ($this->steps() as $step) {
            if (\method_exists($step, 'onError')) {
                $step->onError();
            }
        }

        $this->respond([
            'valid' => false,
            'errors' => $this->submission_result['validation_errors'] ?? [],
            'messages' => $this->globalMessages(),
        ]);
    }

    public function sendSuccess(array $with = []): void
    {
        $this->respond([
            'valid' => true,
            'values' => $this->getStepValues(),
            'notices' => $this->isValidatingStep() ? $this->stepNotices($this->postedStepIndex()) : [],
            'data' => $with,
        ]);
    }

    /**
     * Non blocking warnings of the valid steps 1..n (see StepInterface::notices())
     */
    protected function stepNotices(int $stepIndex): array
    {
        $notices = [];
        for ($i = 0; $i < $stepIndex; $i++) {
            $step = $this->getStepAt($i);
            if ($step && $step->isValid()) {
                $notices = \array_merge($notices, $step->notices());
            }
        }
        return \array_values(\array_unique($notices));
    }

    public function setSuccessTpl(?string $html): self
    {
        $this->successTpl = $html;
        return $this;
    }

    /**
     * Opens sanitizeForm() (protected) to the steps.
     */
    public function sanitizeFormFields(array $posted): array
    {
        return $this->sanitizeForm($posted);
    }
}
