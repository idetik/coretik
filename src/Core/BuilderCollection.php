<?php

namespace Coretik\Core;

use Coretik\Core\Exception\ContainerValueNotFoundException;

class BuilderCollection extends Collection
{
    public function model(int $id)
    {
        return $this->resolve($id)->model($id);
    }

    protected function resolve(int $id)
    {
        foreach ($this->items as $builder) {
            if ($builder->concern($id)) {
                return $builder;
            }
        }

        throw new ContainerValueNotFoundException(sprintf('Identifier "%s" is not defined.', $id));
    }
}
