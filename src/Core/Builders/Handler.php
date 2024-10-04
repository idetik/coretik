<?php

namespace Coretik\Core\Builders;

use Coretik\Core\Builders\Interfaces\BuilderInterface;
use Coretik\Core\Builders\Interfaces\HandlerInterface;
use Coretik\Core\Builders\Interfaces\ModelableInterface;
use Coretik\Core\Models\Interfaces\ModelInterface;

abstract class Handler implements HandlerInterface
{
    protected BuilderInterface|ModelableInterface $builder;

    /**
    * Define the actions to be executed when the handler is invoked
     * @return void
     */
    abstract public function actions(): void;

    /**
    * Temporarily halt the execution of actions by removing the hooks
     * @return void
     */
    abstract public function freeze(): void;

    public function handle(BuilderInterface $builder): void
    {
        $this->builder = $builder;
        $this->actions();
    }

    /**
     * Determine if the handler is concerned with the provided object ID
     * @param int $model_id
     * @return bool
     */
    public function concern(int $model_id): bool
    {
        return $this->builder instanceof ModelableInterface ? $this->builder->concern($model_id) : false;
    }

    /**
     * Retrieve the model instance associated with the given object ID from the builder
     * @param int $model_id
     * @return ModelInterface|null
     */
    public function model(int $model_id): ?ModelInterface
    {
        return $this->builder instanceof ModelableInterface ? $this->builder->model($model_id) : null;
    }

    /**
     * Override this method to implement the custom unfreeze action if needed
     * @return void
     */
    public function unfreeze(): void
    {
        $this->actions();
    }

    /**
     * Execute actions silently by pausing this handler
     * @param callable $callback
     * @return Handler
     */
    public function pause(callable $callback): static
    {
        $this->freeze();
        $callback();
        $this->unfreeze();
        return $this;
    }
}
