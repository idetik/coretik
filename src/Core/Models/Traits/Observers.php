<?php

namespace Coretik\Core\Models\Traits;

trait Observers
{
    protected $observers;

    protected function initializeObservers()
    {
        $this->observers = new \SplObjectStorage();
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
        foreach (static::$observers as $observer) {
            $observer->update($this);
        }
    }
}
