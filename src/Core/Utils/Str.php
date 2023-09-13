<?php

namespace Coretik\Core\Utils;

use Closure;

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

    public static function toString(mixed $value): string
    {
        return match (true) {
            is_string($value) => $value,
            is_bool($value) => $value ? 'true' : 'false',
            is_array($value) => implode(', ', array_map(__METHOD__, $value)),
            $value instanceof Closure => Dump::closure($value),
            default => strval($value)
        };
    }
}
