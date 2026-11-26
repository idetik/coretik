<?php

namespace Coretik\Core\Models\Interfaces;

interface ModelInterface
{
    public function id(): int;
    public function name(): ?string;
    public function on(string $hook_name, $callback, $priority = 10, $count_args = 1);
    public function trigger(string $hook_name, array $args = []);
    public function save(): self;
    public function delete(): void;
}
