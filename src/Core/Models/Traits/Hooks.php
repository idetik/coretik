<?php

namespace Coretik\Core\Models\Traits;

trait Hooks
{
    protected $internal_id;

    public function on($hook_name, $callback, $priority = 10, $count_args = 1)
    {
        \add_action($this->name() . '/' . $this->getInternalId() . '/' . $hook_name, $callback, $priority, $count_args);
        return $this;
    }

    public function trigger($hook_name, $args = [])
    {
        \do_action($this->name() . '/' . $this->getInternalId() . '/' . $hook_name, $this, $args);
        return $this;
    }

    protected function getInternalId()
    {
        if (empty($this->internal_id)) {
            $this->internal_id = \uniqid();
        }
        return $this->internal_id;
    }
}
