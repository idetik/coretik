<?php

namespace Coretik\Core\Query;

class QueryCache
{
    private $cache = [];
    private static $instance;

    public static function instance()
    {
        if (empty(static::$instance)) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->cache);
    }

    public function set(string $key, $object)
    {
        $this->cache[$key] = $object;
    }

    public function get(string $key)
    {
        return $this->cache[$key];
    }
}
