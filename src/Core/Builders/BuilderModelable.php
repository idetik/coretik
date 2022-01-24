<?php

namespace Coretik\Core\Builders;

use Coretik\Core\Models\Model;
use Coretik\Core\Query\Interfaces\QuerierInterface;
use Coretik\Core\Models\Factory;
use Coretik\Core\Models\ModelsInterface as ContainerInterface;
use Coretik\Core\Models\Models as ModelsContainer;
use Coretik\Core\Builders\Interfaces\ModelableInterface;

abstract class BuilderModelable extends Builder implements ModelableInterface
{
    protected $factory;
    protected $querier;
    protected static $models;

    abstract public function wpObject(int $id);
    abstract public function concern(int $objectId): bool;

    public function __construct()
    {
        if (empty(static::$models[$this->getType()])) {
            static::$models[$this->getType()] = new ModelsContainer();
        }
        parent::__construct();
    }

    public function models(): ContainerInterface
    {
        return static::$models[$this->getType()] ?? new ModelsContainer();
    }

    public function factory(callable $factory)
    {
        $this->factory = new Factory($factory, $this, $this->models());
        return $this;
    }

    public function querier(callable $querier)
    {
        $this->querier = $querier;
        return $this;
    }

    public function model($id = null, $initializer = null): Model
    {
        if (\is_null($id)) {
            return $this->factory->create();
        }
        return $this->factory->get($id, $initializer);
    }

    public function query(): QuerierInterface
    {
        return \call_user_func($this->querier, $this);
    }

    //@todo à garder?
    // public static function query(): QuerierInterface
    // {
    //     return (new static())->newQuery();
    // }

    public function hasFactory(): bool
    {
        return isset($this->factory);
    }

    public function hasQuerier(): bool
    {
        return isset($this->querier);
    }
}
