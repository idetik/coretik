<?php

namespace Coretik\Core\Models\Traits;

trait Bootable
{
    protected static $booted = [];

    protected static function bootIfNotBooted()
    {
        if (!isset(static::$booted[static::class])) {
            static::$booted[static::class] = true;
            static::boot();
        }
    }

    protected static function boot()
    {
        // Boot traits
        foreach (class_uses(get_called_class()) as $traitNamespace) {
            $ref = new \ReflectionClass($traitNamespace);
            $traitName = $ref->getShortName();
            $boot = 'boot' . $traitName;
            if (method_exists(static::class, $boot)) {
                forward_static_call([static::class, $boot]);
            }
        }
    }
}
