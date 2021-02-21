<?php

namespace Coretik\Core\Utils\Traits;


trait Singleton
{
    protected function __construct() {}

    final protected function __clone() {}

    final public static function getInstance()
    {
        static $instance = [];
        $called_class = get_called_class();

        if (!isset($instance[$called_class])) {
            $instance[ $called_class ] = new $called_class();
            \do_action(\sprintf('App/Singleton/init/%s', $called_class));
        }

        return $instance[ $called_class ];

    }
}
