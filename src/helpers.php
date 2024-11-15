<?php

use Coretik\App;
use Coretik\Core\Builders\Interfaces\BuilderInterface;
use Coretik\Core\Models\Interfaces\ModelInterface;
use Coretik\Core\Query\Interfaces\QuerierInterface;

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

if (! function_exists('schema')) {
    /**
     * Create an app instance
     *
     * @return BuilderInterface
     */
    function schema(string $name, string $type): BuilderInterface
    {
        return app()->schema($name, $type);
    }
}

if (! function_exists('model')) {
    /**
     * Create an app instance
     *
     * @return ModelInterface
     */
    function model(string $name, ?int $id = null): ModelInterface
    {
        return app()->schema($name)->model($id);
    }
}

if (! function_exists('query')) {
    /**
     * Create an app instance
     *
     * @return QuerierInterface
     */
    function query(string $name): QuerierInterface
    {
        return app()->schema($name)->query();
    }
}
