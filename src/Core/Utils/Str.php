<?php

namespace Coretik\Core\Utils;

class Str
{
    public static function camel(string $src): string
    {
        return lcfirst(str_replace(' ', '', ucwords(strtr($src, '_-', ' '))));
    }
}
