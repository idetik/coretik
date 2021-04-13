<?php

namespace Coretik\Core\Models;

use Psr\Container\ContainerInterface;
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
        return \call_user_func($this->model, null, $this->mediator);
    }

    public function get(int $id)
    {
        try {
            return $this->models->get($id);
        } catch (Exceptions\InstanceNotExistsException $e) {
            try {
                $model = \call_user_func($this->model, $id, $this->mediator);
                $this->models[$id] = $model;
                return $model;
            } catch (Exceptions\CannotResolveException $e) {
                // $this->models[$id] = 'false';
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
