<?php

namespace Coretik\Core\Models\Traits;

use Coretik\Core\Utils\Classes;

trait Initializable
{
    protected function initialize()
    {
        // Initizalize traits
        if (isset(static::$traitInitializers) && array_key_exists(static::class, static::$traitInitializers)) {
            foreach (static::$traitInitializers[static::class] as $method) {
                $this->{$method}();
            }
        } else {
            foreach (Classes::classUsesDeep($this) as $traitNamespace) {
                $ref = new \ReflectionClass($traitNamespace);
                $traitName = $ref->getShortName();
                $initializer = 'initialize' . $traitName;
                if (method_exists($this, $initializer)) {
                    $this->$initializer();
                }
            }
        }

    }
}
