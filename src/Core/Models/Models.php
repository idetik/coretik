<?php

namespace Coretik\Core\Models;

use Psr\Container\ContainerInterface;
use Coretik\Core\Model;

class Models implements ContainerInterface, \ArrayAccess, \IteratorAggregate
{
    protected $objects;

    public function __construct()
    {
        $this->objects = [];
    }

    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->objects[$value->getId()] = $value;
        } else {
            $this->objects[$offset] = $value;
        }
    }

    public function offsetExists($offset)
    {
        return isset($this->objects[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->objects[$offset]);
    }

    public function offsetGet($offset)
    {
        if (is_int($offset) && $this->offsetExists($offset)) {
            return $this->objects[$offset];
        }

        throw new Exceptions\InstanceNotExistsException("{$offset} is not instanciate.");
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->objects);
    }

    public function get($offset)
    {
        return $this->offsetGet($offset);
    }

    public function has($offset)
    {
        return $this->offsetExists($offset);
    }

    public function __invoke($offset)
    {
        return $this->get($offset);
    }
}
