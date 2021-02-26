<?php

namespace Coretik\Services\Notices;

use ArrayIterator;
use IteratorAggregate;

class Container implements \SplSubject, \ArrayAccess, IteratorAggregate
{
    protected $notices;
    protected $observers;
    protected $storage;

    public function __construct(StorageInterface $storage = null)
    {
        $this->observers = new \SplObjectStorage();
        $this->storage = $storage ?? new Storage();
        $this->notices = $this->storage->get()->getArrayCopy();
    }

    public function attach(\SplObserver $observer)
    {
        $this->observers->attach($observer);
    }

    public function detach(\SplObserver $observer)
    {
        $this->observers->detach($observer);
    }

    public function notify()
    {
        foreach ($this->observers as $observer) {
            $observer->update($this);
        }
    }

    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->notices[] = $value;
        } else {
            $this->notices[$offset] = $value;
        }
        $this->notify();
    }

    public function offsetExists($offset)
    {
        return isset($this->notices[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->notices[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->notices[$offset] ?? null;
    }

    public function getIterator()
    {
        return new ArrayIterator($this->notices);
    }
}
