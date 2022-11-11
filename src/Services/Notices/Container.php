<?php

namespace Coretik\Services\Notices;

use Coretik\Services\Notices\Connection\UserConnection;
use Coretik\Services\Notices\Connection\NoConnection;
use ArrayIterator;
use IteratorAggregate;
use SplSubject;
use ArrayAccess;

class Container implements SplSubject, ArrayAccess, IteratorAggregate
{
    protected $notices;
    protected $observers;
    protected $storage;

    public function __construct(?StorageInterface $storage = null)
    {
        $this->observers = new \SplObjectStorage();

        if (empty($storage) && (!defined('WP_CLI') || !WP_CLI)) {
            if (\is_user_logged_in()) {
                $this->setStorage(new UserConnection((int) \get_current_user_id()));
            } elseif (app()->has('session')) {
                $this->setStorage(new SessionConnection(app()->get('session')));
            }
        } else {
            $this->setStorage($storage);
        }

        if (empty($this->storage)) {
            $this->setStorage(new NoConnection());
        }

        $this->notices = $this->storage->get()->getArrayCopy();
    }

    public function storage(): StorageInterface
    {
        return $this->storage;
    }

    public function setStorage(StorageInterface $storage): self
    {
        $this->storage = $storage;
        return $this;
    }

    public function listen(): void
    {
        $this->notify();
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
