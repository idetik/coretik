<?php

namespace Coretik\Core\Builders\Interfaces;

interface HandlerInterface
{
    public function handle(BuilderInterface $builder): void;
    public function freeze(): void;
}
