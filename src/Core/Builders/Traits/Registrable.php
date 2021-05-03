<?php

namespace Coretik\Core\Builders\Traits;

trait Registrable
{
    protected $registered = false;

    public function registrable(): bool
    {
        return !$this->registered;
    }

    public function register(): void
    {
        if ($this->registrable()) {
            $this->registerAction();
            $this->registered = true;
        }
    }
}
