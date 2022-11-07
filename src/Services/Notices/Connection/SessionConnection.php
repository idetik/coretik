<?php

namespace Coretik\Services\Notices\Connection;

use ArrayIterator;
use Coretik\Services\Notices\StorageInterface;

class SessionConnection implements StorageInterface
{
    const KEY = 'app_notices';

    protected $session;

    public function __construct($session)
    {
        $this->session = $session;
    }

    public function get(): ArrayIterator
    {
        if (!$this->session->has(static::KEY)) {
            return new ArrayIterator;
        }

        return $this->session->get(static::KEY);
    }

    public function set(ArrayIterator $iterator)
    {
        $this->session->set(static::KEY, $iterator);
    }
}
