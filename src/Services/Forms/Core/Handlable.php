<?php

namespace Coretik\Services\Forms\Core;

interface Handlable
{
    public function id();
    public function isRunnable();
    public function process();
    public function getRules(): array;
}
