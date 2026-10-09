<?php

namespace Coretik\Tests\Unit\Models;

use Coretik\Core\Models\Model;

/**
 * Model using the Metable trait methods, for mocks.
 */
abstract class MetableModel extends Model
{
    abstract public function protectedMetaKeys(bool $local = true): array;

    abstract public function isProtectedMeta(string $key): bool;
}
