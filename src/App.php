<?php

namespace Coretik;

use Coretik\Core\Container;

class App
{
    private $container;
    protected static $instance = null;

    protected function __construct($container = [])
    {
        if (is_array($container)) {
            $container = new Container($container);
        }

        $this->container = $container;
    }

    public static function instance()
    {
        return static::$instance;
    }

    public static function run($containers)
    {
        if (empty(static::$instance)) {
            static::$instance = new static($containers);
        }
    }

    public static function __callStatic($method, $args)
    {
        return \call_user_func_array([static::instance(), $method], $args);
    }

    public function __call($method, $args)
    {
        if ($this->container->has($method)) {
            $obj = $this->container->get($method);
            if (\is_callable($obj)) {
                return \call_user_func_array($obj, $args);
            }
            return $obj;
        }
    }

    public function get(string $key)
    {
        if ($this->container->has($key)) {
            return $this->container->get($key);
        }
    }

    public function __get($prop)
    {
        return $this->get($prop);
    }
}
