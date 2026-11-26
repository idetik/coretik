<?php

use Coretik\App;
use Coretik\Core\Builders\Interfaces\BuilderInterface;
use Coretik\Core\Models\Interfaces\ModelInterface;
use Coretik\Core\Query\Interfaces\QuerierInterface;

if (! function_exists('coretik')) {
    /**
     * The coretik application
     */
    function coretik(): ?App
    {
        return App::instance();
    }
}

/*
 * Acorn (Laravel) defines its own app() helper, used by Laravel components: with Acorn, use coretik() or app('coretik').
 */
if (! function_exists('app') && ! class_exists(\Roots\Acorn\Application::class)) {
    /**
     * @deprecated 2.0 Use coretik(): app() is reserved to Acorn (Laravel) when it is installed
     */
    function app(): ?App
    {
        return App::instance();
    }
}

if (! function_exists('schema')) {
    function schema(string $name, string $type): ?BuilderInterface
    {
        return coretik()->schema($name, $type);
    }
}

if (! function_exists('model')) {
    function model(string $name, ?int $id = null): ModelInterface
    {
        return coretik()->schema()->modelable($name)->model($id);
    }
}

if (! function_exists('query')) {
    function query(string $name): QuerierInterface
    {
        return coretik()->schema()->modelable($name)->query();
    }
}
