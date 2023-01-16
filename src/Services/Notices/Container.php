<?php

namespace Coretik\Services\Notices;

use Coretik\Services\Notices\Connection\UserConnection;
use Coretik\Services\Notices\Connection\NoConnection;
use ArrayIterator;
use IteratorAggregate;
use SplSubject;
use ArrayAccess;
use Traversable;

class Container implements SplSubject, ArrayAccess, IteratorAggregate
{
    protected $notices;
    protected $observers;
    protected $storage;
    protected $initialized = false;

    public function __construct(?StorageInterface $storage = null)
    {
        $this->observers = new \SplObjectStorage();

        if (empty($storage)) {
            if ((!defined('WP_CLI') || !WP_CLI) && \did_action('coretik/app/launched')) {
                $this->initialize();
            }
        } else {
            $this->setStorage($storage);
            $this->initialized = true;
        }

        if (empty($this->storage)) {
            $this->setStorage(new NoConnection());
        }
    }

    protected function initialize()
    {
        if (\is_user_logged_in()) {
            $this->setStorage(new UserConnection((int) \get_current_user_id()));
        } elseif (app()->has('session')) {
            $this->setStorage(new SessionConnection($this->app->get('session')));
        }
        $this->notices = $this->storage->get()->getArrayCopy();
        $this->initialized = true;
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
        if (!$this->initialized) {
            $this->initialize();
        }
        $this->notify();
    }

    public function attach(\SplObserver $observer): void
    {
        $this->observers->attach($observer);
    }

    public function detach(\SplObserver $observer): void
    {
        $this->observers->detach($observer);
    }

    public function notify(): void
    {
        foreach ($this->observers as $observer) {
            $observer->update($this);
        }
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_null($offset)) {
            $this->notices[] = $value;
        } else {
            $this->notices[$offset] = $value;
        }
        $this->notify();
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->notices[$offset]);
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->notices[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->notices[$offset] ?? null;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->notices);
    }
}
