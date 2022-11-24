<?php

namespace Coretik\Core\Utils;

class Dump
{
    protected static function declaresArray(\ReflectionParameter $reflectionParameter): bool
    {
        $reflectionType = $reflectionParameter->getType();

        if (!$reflectionType) {
            return false;
        };

        $types = $reflectionType instanceof \ReflectionUnionType
            ? $reflectionType->getTypes()
            : [$reflectionType];

        return \in_array('array', \array_map(fn(\ReflectionNamedType $t) => $t->getName(), $types));
    }

    protected static function getClass(\ReflectionParameter $reflectionParameter)
    {
        return  $reflectionParameter->getType() && !$reflectionParameter->getType()->isBuiltin()
            ? new ReflectionClass($reflectionParameter->getType()->getName())
            : null;
    }

    public static function closure(\Closure $c)
    {
        $r = new \ReflectionFunction($c);
        $str = '';
        $lines = file($r->getFileName());

        if ($r->getStartLine() !== $r->getEndLine()) {
            $str = 'function (';
            $params = [];
            foreach ($r->getParameters() as $p) {
                $s = '';
                if (static::declaresArray($p)) {
                    $s .= 'array ';
                } elseif (!empty(static::getClass($p))) {
                    $s .= static::getClass($p)->name . ' ';
                }
                if ($p->isPassedByReference()) {
                    $s .= '&';
                }
                $s .= '$' . $p->name;
                if ($p->isOptional()) {
                    $s .= ' = ' . var_export($p->getDefaultValue(), true);
                }
                $params[] = $s;
            }
            $str .= implode(', ', $params);
            $str .= '){' . PHP_EOL;

            for ($l = $r->getStartLine(); $l < $r->getEndLine(); $l++) {
                $str .= $lines[$l];
            }
        } else {
            $line = $lines[$r->getStartLine() - 1];
            $pos = strpos($line, 'function') ?: strpos($line, 'fn') ?: 0;
            $line = \substr($line, $pos);
            $str .= $line;
        }

        return $str;
    }
}
