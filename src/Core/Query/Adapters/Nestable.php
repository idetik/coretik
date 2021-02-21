<?php

namespace Coretik\Core\Query\Adapters;

trait Nestable
{
    public function nest(self $query)
    {
        if (empty($this->nestable)) {
            return $this;
        }
        foreach ($this->nestable as $parameter) {
            if (!empty($query->get($parameter))) {
                $current = $this->get($parameter) ?? [];
                $current[] = $query->get($parameter);
                $this->set($parameter, $current);
            }
        }
        return $this;
    }
}
