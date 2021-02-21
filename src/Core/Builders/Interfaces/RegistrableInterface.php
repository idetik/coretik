<?php

namespace Coretik\Core\Builders\Interfaces;

interface RegistrableInterface
{
    public function registerAction(): void;
    public function register(): void;
}
