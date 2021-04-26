<?php

namespace Coretik\Core\Builders\Interfaces;

interface BuilderInterface
{
    public function priority(): int;
    public function getName(): string;
    public function getType(): string;
    public function handler(HandlerInterface $handler): void;
    public function runHandlers(): void;
}
