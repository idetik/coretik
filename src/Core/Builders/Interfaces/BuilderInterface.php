<?php

namespace Coretik\Core\Builders\Interfaces;

interface BuilderInterface
{
    public function priority(): int;
    public function getName(): string;
    public function getType(): string;
    public function handler(HandlerInterface $handler): self;
    public function runHandlers(): self;
    public function attach(string $name, callable $service): self;
}
