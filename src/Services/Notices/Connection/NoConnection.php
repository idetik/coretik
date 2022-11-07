<?php

namespace Coretik\Services\Notices\Connection;

use ArrayIterator;
use Coretik\Services\Notices\StorageInterface;

class NoConnection implements StorageInterface
{
    protected $notices;

    public function get(): ArrayIterator
    {
        return $this->notices ?? new ArrayIterator();
    }

    public function set(ArrayIterator $iterator)
    {
        $this->notices = $iterator;
    }
}
