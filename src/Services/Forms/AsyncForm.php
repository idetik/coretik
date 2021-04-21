<?php

namespace Coretik\Services\Forms;

abstract class AsyncForm extends BaseForm implements Asyncable
{
    const SCROLL_TO_ERRORS = true;
    const SCROLL_TO_SUCCESS = true;

    public function endpoint(): string
    {
        return \admin_url('admin-ajax.php');
    }

    public function wpAjaxAction(): string
    {
        return 'form-' . $this->getName();
    }

    /**
     * Restrict for authenticated WP user or not
     */
    public function public(): bool
    {
        return true;
    }

    public function view($data = [])
    {
        parent::view(
            $data + [
                'ajax_refresh' => true,
                'ajax_endpoint' => $this->endpoint(),
                'ajax_action' => $this->wpAjaxAction(),
                'scroll_to_errors' => static::SCROLL_TO_ERRORS,
                'scroll_to_success' => static::SCROLL_TO_SUCCESS,
            ]
        );
    }

    protected function onValidationError()
    {
        parent::onValidationError();
        if (static::SCROLL_TO_ERRORS) {
            $this->data['errors'][] = 'This form contains errors, please check your entries';
        }
    }
}
