<?php

namespace Coretik\Core\Models\Traits;

use Coretik\Core\Utils\Classes;

trait Bootable
{
    protected static $booted = [];
    protected static $traitInitializers = [];
    protected static $traitBooted = [];

    public static function bootIfNotBooted()
    {
        if (!isset(static::$booted[static::class])) {
            static::$booted[static::class] = true;
            static::boot();
        }
    }

    protected static function boot()
    {
        static::bootTraits();
    }

    protected static function bootTraits()
    {
        // Boot traits
        $class = static::class;
        $booted = [];
        $traits = Classes::classUsesDeep($class);

        static::$traitInitializers[$class] = [];

        foreach ($traits as $trait) {

            // Boot once
            $method = 'boot'.Classes::basename($trait).'Once';
            if (method_exists($class, $method) && ! in_array($method, static::$traitBooted)) {
                forward_static_call([$class, $method]);
                static::$traitBooted[] = $method;
            }

            // Classic boot
            $method = 'boot'.Classes::basename($trait);
            if (method_exists($class, $method) && ! in_array($method, $booted)) {
                forward_static_call([$class, $method]);
                $booted[] = $method;
            }

            // Register initializers
            if (method_exists($class, $method = 'initialize'.Classes::basename($trait))) {
                static::$traitInitializers[$class][] = $method;
                static::$traitInitializers[$class] = array_unique(
                    static::$traitInitializers[$class]
                );
            }
        }
    }
}
