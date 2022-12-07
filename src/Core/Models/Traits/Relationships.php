<?php

namespace Coretik\Core\Models\Traits;

use Coretik\Core\Builders\Interfaces\BuilderInterface;
use Coretik\Core\Models\Interfaces\ModelInterface;
use Coretik\Core\Models\Exceptions\CannotResolveException;
use Coretik\Core\Exception\ContainerValueNotFoundException;
use Coretik\Core\Collection;

trait Relationships
{
    protected function belongsTo(string|BuilderInterface $builder): ?ModelInterface
    {
        $builder = $this->resolveBuilder($builder);
        try {
            $parent_id = $this->parentId();

            if (empty($parent_id)) {
                return null;
            }

            return $builder->model($parent_id);
        } catch (CannotResolveException $e) {
            return null;
        }
    }

    protected function hasMany(string|BuilderInterface $builder): Collection
    {
        $builder = $this->resolveBuilder($builder);
        return $builder->query()->childOf($this->id())->all()->collection();
    }

    protected function hasOne(string|BuilderInterface $builder): Collection
    {
        $builder = $this->resolveBuilder($builder);
        return $builder->query()->childOf($this->id())->limit(1)->first();
    }

    protected function resolveBuilder(string|BuilderInterface $builder): BuilderInterface
    {
        if ($builder instanceof BuilderInterface) {
            return $builder;
        }

        if (!empty(($object = app()->schema()->get($builder)))) {
            return $object;
        }

        throw new ContainerValueNotFoundException;
    }
}
