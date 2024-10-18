<?php

namespace Coretik\Services\Forms;

use Coretik\Services\Forms\Core\Asyncable;

abstract class AsyncForm extends Form implements Asyncable
{
    const SCROLL_TO_ERRORS = true;
    const SCROLL_TO_SUCCESS = true;

    public function endpoint(): string
    {
        return \admin_url('admin-ajax.php');
    }

    public function wpAjaxAction(): string
    {
        return 'form-' . $this->id();
    }

    /**
     * Restrict for authenticated WP user or not
     */
    public function public(): bool
    {
        return true;
    }

    public function view($data = [], bool $return = false)
    {
        return parent::view(
            array_merge($this->view_data, $data, [
                'ajax_refresh' => true,
                'ajax_endpoint' => $this->endpoint(),
                'ajax_action' => $this->wpAjaxAction(),
                'scroll_to_errors' => static::SCROLL_TO_ERRORS,
                'scroll_to_success' => static::SCROLL_TO_SUCCESS,
                'persistent_data' => array_combine($this->persistentMetaKey, array_map(fn ($key) => $this->getMeta($key), $this->persistentMetaKey)),
            ]),
            $return
        );
    }

    public function process()
    {
        if ($this->isSubmitting()) {
            if (!empty($_REQUEST['persistent-data'])) {
                $this->persistentMetaKey = array_merge(
                    array_keys($_REQUEST['persistent-data']),
                    $this->persistentMetaKey
                );
            }
        }
        parent::process();
    }

    protected function onValidationError()
    {
        parent::onValidationError();
        if (static::SCROLL_TO_ERRORS) {
            $this->view_data['errors'][] = __('Ce formulaire contient des erreurs, veuillez vérifier votre saisie.', 'coretik');
        }
    }

    protected function redirectTo(string $url, int $status = 200, array $data = []): void
    {
        $response = [
            'success' => true,
            'data' => [
                'redirect_url' => $url,
                ...$data
            ]
        ];
        \wp_send_json($response, 200);
        exit;
    }
}
