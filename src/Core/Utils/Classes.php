<?php

namespace Coretik\Core\Utils;

class Classes
{
    public static function classUsesDeep($class, $autoload = true): array
    {
        $traits = [];
        do {
            $traits = \array_merge(\class_uses($class, $autoload), $traits);
        } while ($class = \get_parent_class($class));
        foreach ($traits as $trait => $same) {
            $traits = \array_merge(\class_uses($trait, $autoload), $traits);
        }
        return \array_unique($traits);
    }
}
