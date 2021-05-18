<?php

namespace Coretik\Core\Models\Traits;

use Coretik\Core\Utils\Classes;

trait Initializable
{
    protected function initialize()
    {
        // Initizalize traits
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
