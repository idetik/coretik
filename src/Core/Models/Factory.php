<?php

namespace Coretik\Core\Models;

use Coretik\Core\Models\ModelsInterface as ContainerInterface;
use Coretik\Core\Interfaces\CollectionInterface;
use Coretik\Core\Builders\Interfaces\ModelableInterface;

class Factory
{
    protected $mediator;
    protected $models;
    protected $model;

    public function __construct(callable $model, ModelableInterface $mediator, ContainerInterface $models = null)
    {
        if (empty($models)) {
            $models = new Models();
        }
        $this->model = $model;
        $this->models = $models;
        $this->mediator = $mediator;
    }

    public function create()
    {
        $model = \call_user_func($this->model, null, $this->mediator, ['id' => null, 'initializer' => null]);
        if (empty($model->name())) {
            $model->setName($this->mediator->getName());
        }
        return $model;
    }

    public function get(int $id, $initializer = null, $refresh = false)
    {
        try {
            if ($refresh && $this->models->offsetExists($id)) {
                $this->models->offsetUnset($id);
            }
            return $this->models->get($id);
        } catch (Exceptions\InstanceNotExistsException $e) {
            try {
                $model = \call_user_func($this->model, $initializer ?? $id, $this->mediator, ['id' => $id, 'initializer' => $initializer]);
                $this->models[$id] = $model;
                return $model;
            } catch (Exceptions\CannotResolveException $e) {
                throw $e;
            }
        }
    }

    public function __invoke(int $id = 0)
    {
        if ($id > 0) {
            return $this->get($id);
        }
        return $this->create();
    }
}
