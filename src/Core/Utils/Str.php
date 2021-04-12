<?php

namespace Coretik\Core\Utils;

class Str
{
    public static function camel(string $src): string
    {
        return lcfirst(str_replace(' ', '', ucwords(strtr($src, '_-', ' '))));
    }

    public static function humanize(string $entry, string $separator = '_'): string
    {
        return \trim(\strtolower((string) \preg_replace(['/([A-Z])/', \sprintf('/[%s\s]+/', $separator)], ['_$1', ' '], $entry)));
    }
}
