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
    protected ?string $modelClass = null;

    public function __construct(callable|string $model, ModelableInterface $mediator, ?ContainerInterface $models = null)
    {
        if (empty($models)) {
            $models = new Models();
        }

        if (\is_string($model)) {
            $this->modelClass = \ltrim($model, '\\');
            $model = fn ($initializer, $mediator, $args) => new $model($initializer, $mediator, $args);
        }

        $this->model = $model;
        $this->models = $models;
        $this->mediator = $mediator;
    }

    /**
     * The model class name, null when the factory is a callable
     */
    public function modelClass(): ?string
    {
        return $this->modelClass;
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
