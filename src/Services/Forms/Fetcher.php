<?php

namespace Coretik\Services\Forms;

use Coretik\Services\Forms\Core\Asyncable;

/**
 * Renders a form through AJAX (public action "fetch_form", see scripts/Fetcher.js), e.g. to open it in a modal.
 *
 * The action is public: only the forms explicitly allowed can be rendered.
 *
 *   $container['forms.fetcher'] = fn () => new Fetcher(['registration']);
 *   coretik()->get('forms.fetcher')->listen();
 *
 * Answer: { success: true, data: { form: string (HTML), modals: string (HTML of the modals service) } }
 */
class Fetcher
{
    public const ACTION = 'fetch_form';

    /**
     * @param string[] $allowed Ids of the forms (Form::id()) that can be rendered
     */
    public function __construct(protected array $allowed = [])
    {
    }

    public function listen(): void
    {
        \add_action('wp_ajax_' . static::ACTION, [$this, 'fetchForm']);
        \add_action('wp_ajax_nopriv_' . static::ACTION, [$this, 'fetchForm']);
    }

    public function allow(string ...$formIds): self
    {
        $this->allowed = \array_values(\array_unique(\array_merge($this->allowed, $formIds)));
        return $this;
    }

    public function isAllowed(string $formId): bool
    {
        return '' !== $formId && \in_array($formId, $this->allowed, true);
    }

    public function fetchForm(): void
    {
        $formId = \sanitize_key(\wp_unslash($_POST['formId'] ?? ''));

        if (!$this->isAllowed($formId) || !\coretik()->forms()->has($formId)) {
            \wp_send_json_error('Form not found', 404);
        }

        $form = \coretik()->forms()->get($formId);
        $form = \apply_filters('coretik/forms/fetcher/form', $form, $formId);
        if (!$form instanceof Form && !$form instanceof Asyncable) {
            \wp_send_json_error('Form not found', 404);
        }

        $html = $form->view([], true);

        $modals = '';
        if (\coretik()->has('modals')) {
            \ob_start();
            \coretik()->modals()->render();
            $modals = (string)\ob_get_clean();
        }

        \wp_send_json_success([
            'form' => $html,
            'modals' => $modals,
        ]);
    }
}
