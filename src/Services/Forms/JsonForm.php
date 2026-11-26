<?php

namespace Coretik\Services\Forms;

use Coretik\Services\Forms\Concerns\RespondsWithJson;
use Coretik\Services\Forms\Core\ConfigInterface;

/**
 * One step AJAX form answering JSON, driven by the forms scripts ([data-form], scripts/JsonForm.js).
 *
 * Lifecycle (Core\Handler::handleAsyncRequest()): process() -> validation -> run() -> onSuccess(): JSON answer, then exit.
 * On any error (validation, Core\Exception thrown by run(), nonce, rate limit, spam), onError() answers { valid: false }.
 *
 *   class Newsletter extends JsonForm {
 *       const ID = 'newsletter';
 *       public function getRules(): array { return ['email' => ['name' => 'Email', 'constraints' => ['required' => true, 'email' => true]]]; }
 *       protected function isValidContext(): bool { return true; }
 *       protected function run(): void { subscribe($this->getValues()['email']); }
 *   }
 *
 * Template: templates/forms/{id}.php, opened and closed by the parts/json-form-open and parts/json-form-close parts.
 */
abstract class JsonForm extends AsyncForm
{
    use RespondsWithJson;

    /**
     * HTML (built server side, never raw user input) injected in [data-form-success] after a valid submission.
     */
    protected ?string $successTpl = null;

    /**
     * Plain text message displayed in [data-form-success] after a valid submission, when there is no successTpl.
     */
    protected ?string $successMessage = null;

    /**
     * Without $id, the ID constant of the class is used (lazy forms: Core\Handler builds them without argument).
     */
    public function __construct(?string $id = null, array $values = [], $template = null, $form_name = null, ?ConfigInterface $config = null, array $metas = [])
    {
        $id ??= \defined(static::class . '::ID') ? (string)\constant(static::class . '::ID') : null;
        if (empty($id)) {
            throw new \LogicException(\sprintf('%s: no form id, define an ID constant or pass it to the constructor.', static::class));
        }

        parent::__construct($id, $values, $template, $form_name, $config, $metas);
    }

    public function view($data = [], bool $return = false)
    {
        return parent::view($this->jsonViewData(\array_merge($data, [
            'successMessage' => $this->successMessage(),
        ])), $return);
    }

    public function successMessage(): string
    {
        return $this->successMessage ?? \__('Votre demande a bien été envoyée.', 'coretik');
    }

    public function setSuccessMessage(?string $message): self
    {
        $this->successMessage = $message;
        return $this;
    }

    public function setSuccessTpl(?string $html): self
    {
        $this->successTpl = $html;
        return $this;
    }

    /**
     * Answers the valid submission. Override (calling parent last) to set a successTpl or to redirect.
     */
    protected function onSuccess()
    {
        $this->sendSuccess($this->successTpl ? ['successTpl' => $this->successTpl] : []);
    }

    protected function onError()
    {
        $this->respond([
            'valid' => false,
            'errors' => $this->formatErrors($this->getValidation()?->getErrors() ?? []),
            'messages' => $this->globalMessages(),
        ]);
    }

    public function sendSuccess(array $with = []): void
    {
        $this->respond([
            'valid' => true,
            'values' => $this->formatValues($this->getValues() ?: []),
            'data' => $with,
        ]);
    }
}
