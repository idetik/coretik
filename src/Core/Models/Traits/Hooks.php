<?php

namespace Coretik\Core\Models\Traits;

trait Hooks
{
    protected string $internal_id;

    public function on(string $hook_name, $callback, $priority = 10, $count_args = 1)
    {
        \add_action($this->hookName($hook_name), $callback, $priority, $count_args);
        return $this;
    }

    public function trigger(string $hook_name, array $args = [])
    {
        \do_action($this->hookName($hook_name), $this, $args);
        return $this;
    }

    protected function getInternalId(): string
    {
        if (empty($this->internal_id)) {
            $this->internal_id = \uniqid();
        }
        return $this->internal_id;
    }

    protected function hookName(string $action): string
    {
        return $this->name() . '/' . $this->getInternalId() . '/' . $action;
    }
}
