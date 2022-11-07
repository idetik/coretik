<?php

namespace Coretik\Services\Notices\Connection;

use ArrayIterator;
use Coretik\Services\Notices\StorageInterface;

class UserConnection implements StorageInterface
{
    const KEY = 'app_notices';

    protected $user_id;

    public function __construct(int $user_id)
    {
        $this->user_id = $user_id;
    }

    public function get(): ArrayIterator
    {
        return \get_user_meta($this->user_id, static::KEY, true) ?: new ArrayIterator();
    }

    public function set(ArrayIterator $iterator)
    {
        \update_user_meta($this->user_id, static::KEY, $iterator);
    }
}
