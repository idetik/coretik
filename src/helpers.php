<?php

use Coretik\App;
use Coretik\Core\Collection;

if (! function_exists('app')) {
    /**
     * Create an app instance
     *
     * @return \Coretik\App
     */
    function app()
    {
        return App::instance();
    }
}

if (! function_exists('collect')) {
    /**
     * Array to collection
     *
     * @return \Coretik\App
     */
    function collect(array $array): Collection
    {
        return new Collection($array);
    }
}
