<?php

namespace Coretik\Tests\Unit\Forms\Fixtures;

class LazyWrongIdForm extends LazyContactForm
{
    const ID = 'declared-id';

    public function id()
    {
        return 'real-id';
    }
}
