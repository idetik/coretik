<?php

namespace Coretik\Tests\Unit\Forms\Fixtures;

class LazyCustomActionForm extends LazyContactForm
{
    const ID = 'lazy-custom';
    const AJAX_ACTION = 'custom-action';

    public function wpAjaxAction(): string
    {
        return 'custom-action';
    }
}
