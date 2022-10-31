<?php

namespace Coretik\Core\Models;

use Coretik\Core\Model;

class Models implements ModelsInterface, \ArrayAccess, \IteratorAggregate
{
    protected $objects;

    public function __construct()
    {
        $this->objects = [];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_null($offset)) {
            $this->objects[$value->getId()] = $value;
        } else {
            $this->objects[$offset] = $value;
        }
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->objects[$offset]);
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->objects[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        if (is_int($offset) && $this->offsetExists($offset)) {
            return $this->objects[$offset];
        }

        throw new Exceptions\InstanceNotExistsException("{$offset} is not instanciate.");
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->objects);
    }

    public function get($offset)
    {
        return $this->offsetGet($offset);
    }

    public function has(int $offset): bool
    {
        return $this->offsetExists($offset);
    }

    public function __invoke($offset)
    {
        return $this->get($offset);
    }
}
