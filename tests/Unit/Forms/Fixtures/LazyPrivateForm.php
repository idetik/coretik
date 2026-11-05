<?php

namespace Coretik\Tests\Unit\Forms\Fixtures;

class LazyPrivateForm extends LazyContactForm
{
    const ID = 'lazy-private';

    public static int $runnableChecks = 0;

    public function public(): bool
    {
        return false;
    }
}
