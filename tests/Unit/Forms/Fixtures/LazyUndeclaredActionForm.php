<?php

namespace Coretik\Tests\Unit\Forms\Fixtures;

class LazyUndeclaredActionForm extends LazyContactForm
{
    const ID = 'lazy-undeclared';

    public static int $runnableChecks = 0;

    public function wpAjaxAction(): string
    {
        return 'something-else';
    }
}
