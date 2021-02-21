<?php

namespace Coretik\Core\Builders;

use Coretik\Core\Models\Model;
use Coretik\Core\Query\Interfaces\QuerierInterface;
use Coretik\Core\Models\Factory;
use Coretik\Core\Models\Models as ModelsContainer;
use Coretik\Core\Builders\Interfaces\ModelableInterface;
use Psr\Container\ContainerInterface;

abstract class BuilderModelable extends Builder implements ModelableInterface
{
    protected $factory;
    protected $querier;
    protected static $models;

    abstract public function wpObject(int $id);

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
        $this->factory = new Factory($factory, $this->models());
    }

    public function querier(callable $querier)
    {
        $this->querier = $querier;
    }

    public function model($initializer = null): Model
    {
        if (\is_null($initializer)) {
            return $this->factory->create();
        }
        return $this->factory->get($initializer);
    }

    public function newQuery(): QuerierInterface
    {
        return \call_user_func($this->querier, $this);
    }

    public static function query(): QuerierInterface
    {
        return (new static())->newQuery();
    }

    public function hasFactory(): bool
    {
        return isset($this->factory);
    }

    public function hasQuerier(): bool
    {
        return isset($this->querier);
    }
}
