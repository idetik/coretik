<?php

namespace Coretik\Tests\Unit\Forms\Fixtures;

class NoIdForm extends LazyContactForm
{
    const ID = null;

    public function id()
    {
        return 'no-id';
    }
}
