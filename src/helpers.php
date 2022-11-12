<?php

use Coretik\App;

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
