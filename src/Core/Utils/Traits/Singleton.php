<?php

namespace Coretik\Core\Utils\Traits;


trait Singleton
{
    protected function __construct() {}

    final protected function __clone() {}

    final public static function instance()
    {
        static $instance = [];
        $called_class = get_called_class();

        if (!isset($instance[$called_class])) {
            $instance[ $called_class ] = new $called_class();
            \do_action(\sprintf('coretik/singleton/init/%s', $called_class));
        }

        return $instance[ $called_class ];
    }
}
