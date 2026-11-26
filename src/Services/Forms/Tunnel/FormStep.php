<?php

namespace Coretik\Services\Forms\Tunnel;

use Coretik\Services\Forms\Core\JsonResponse;
use Coretik\Services\Forms\Core\Validation\Validation;
use Coretik\Services\Forms\Core\Validation\Validator;
use Coretik\Services\Forms\Tunnel\Step\StepInterface;
use Coretik\Services\Forms\Tunnel\Step\SummaryStepInterface;

/**
 * Step of a tunnel: declares its rules (same format as Form::getRules()), its title, its instructions
 * and its template (templates/forms/steps/{STEP_NAME}.php).
 *
 * By default a step depends on the previous one: it is only valid when the previous steps are valid too.
 *
 *   class ContactStep extends FormStep {
 *       const STEP_NAME = 'contact';
 *       public string $title = 'Contact';
 *       public function getRules(): array {
 *           return ['email' => ['name' => 'Email', 'constraints' => ['required' => true, 'email' => true]]];
 *       }
 *   }
 *
 * @phpstan-consistent-constructor
 */
abstract class FormStep implements StepInterface
{
    const STEP_NAME = null;

    public string $title = '';
    public string $instructions = '';
    public ?string $submitLabel = null;
    protected bool $valid = false;
    /** @var StepInterface[] */
    protected array $dependencies = [];
    protected Validation $validation;

    abstract public function getRules(): array;

    public function __construct(
        protected TunnelFormInterface $form
    ) {
        $this->initializeStep();
    }

    public static function make(TunnelFormInterface $form): static
    {
        return new static($form);
    }

    /**
     * Called by the constructor: set the default title, instructions...
     */
    protected function initializeStep(): void
    {
        //
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getInstructions(): string
    {
        return $this->instructions;
    }

    public function getSubmitLabel(): ?string
    {
        return $this->submitLabel;
    }

    public function getStepIndex(): int
    {
        return (int)$this->form->steps()->search($this, true);
    }

    public function getTunnel(): TunnelFormInterface
    {
        return $this->form;
    }

    public function getFormName(): string
    {
        return $this->form->getFormName();
    }

    public function isOpen(): bool
    {
        foreach ($this->getDependencies() as $dependency) {
            if (!$dependency->isValid()) {
                return false;
            }
        }
        return true;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * Last step holding fields (the summary is not counted).
     */
    public function isTheLastStep(): bool
    {
        return $this->form->steps()->filter(fn ($step) => !$step->isSummary())->last() === $this;
    }

    public function isSummary(): bool
    {
        return $this instanceof SummaryStepInterface;
    }

    public function getDependencies(): array
    {
        if (empty($this->dependencies)) {
            $previousStep = $this->form->getStepBefore($this);
            if ($previousStep) {
                $this->addDependency($previousStep);
            }
        }

        return $this->dependencies;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function setInstructions(string $instructions): self
    {
        $this->instructions = $instructions;
        return $this;
    }

    public function setSubmitLabel(?string $submitLabel): self
    {
        $this->submitLabel = $submitLabel;
        return $this;
    }

    protected function getStepFields(): array
    {
        return \array_intersect_key($this->form->getFields(), $this->getRules());
    }

    protected function getStepPostedData(): array
    {
        $posted_data = $_POST[$this->form->getFormName()] ?? [];
        if (!\is_array($posted_data)) {
            return [];
        }

        $posted_data = $this->form->sanitizeFormFields($posted_data);
        return \array_intersect_key($posted_data, $this->getRules());
    }

    public function getErrors(): array
    {
        return JsonResponse::errors($this->getValidation()?->getErrors() ?? [], $this->getStepIndex());
    }

    public function getValues(): array
    {
        return JsonResponse::values(
            $this->getValidation()?->getData() ?? [],
            fn (string $field) => (string)$this->form->fieldLabel($field),
            fn (string $field) => $this->fieldContainsPrivateData($field),
            $this->getStepIndex()
        );
    }

    public function getValue(string $fieldname): mixed
    {
        return $this->getValidation()?->getData()[$fieldname] ?? null;
    }

    public function getValidation(): ?Validation
    {
        return $this->validation ?? null;
    }

    protected function needValidation(): bool
    {
        return !isset($this->validation);
    }

    /**
     * Validates the posted data of the step, once its dependencies are valid. Runs once per request.
     */
    public function validate(): self
    {
        if (!$this->needValidation()) {
            return $this;
        }

        foreach ($this->getDependencies() as $dependency) {
            if (!$dependency->validate()->isValid()) {
                $this->valid = false;
                return $this;
            }
        }

        $this->validation = new Validation($this);

        // Step without field (summary...): Validation::run() fails on empty data
        if (empty($this->getRules())) {
            $this->valid = true;
            return $this;
        }

        $posted = $this->getStepPostedData();

        // E.g. a step holding only checkboxes: nothing is posted, but the validation must run
        foreach ($this->getRules() as $field_key => $data) {
            if (!\array_key_exists($field_key, $posted)) {
                $posted[$field_key] = '';
            }
        }

        $this->validation->setData($posted);
        foreach ($this->getStepFields() as $field_key => $data) {
            $validator = new Validator();
            foreach ($data['constraints'] as $constraint) {
                $validator->addConstraint($constraint);
            }
            $this->validation->setValidator($field_key, $validator);
        }

        $this->validation->run();
        $this->valid = $this->validation->isValid();

        return $this;
    }

    public function withDependencies(array $dependencies): self
    {
        foreach ($dependencies as $dependency) {
            $this->addDependency($dependency);
        }
        return $this;
    }

    public function addDependency(StepInterface $step): self
    {
        $this->dependencies[] = $step;
        return $this;
    }

    public function getRgpdDataCategories(): array
    {
        $categories = [];
        foreach ($this->getRules() as $data) {
            if (isset($data['rgpd']['category'])) {
                $category = $data['rgpd']['category'];
                $categories[$category instanceof \BackedEnum ? (string)$category->value : (string)$category][] = $data['name'];
            }
        }
        return $categories;
    }

    public function fieldContainsPrivateData(string $field): bool
    {
        return JsonResponse::isPrivate($this->getRules(), $field);
    }

    public function payload(): array
    {
        return $this->getValidation()?->getData() ?? [];
    }

    public function notices(): array
    {
        return [];
    }

    /**
     * Template: templates/forms/steps/{STEP_NAME}.php, else steps/{tunnel template}-{n}.php (n: 1-based)
     */
    public function render(bool $return = false): ?string
    {
        $template = static::STEP_NAME ?? ($this->form->getTemplate() . '-' . ($this->getStepIndex() + 1));

        return $this->form->viewPart('steps/' . $template, ['step' => $this], $return);
    }
}
