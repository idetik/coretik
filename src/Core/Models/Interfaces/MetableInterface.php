<?php

namespace Coretik\Core\Models\Interfaces;

/**
 * Models with declared metas (see the Metable trait)
 */
interface MetableInterface
{
    public function meta(string $key, $default = null, $raw = false);
    public function hasMeta(string $key);
    public function metaDefinition($key);
    public function metaKeys(bool $local = true): array;
    public function isProtectedMeta(string $key): bool;
    public function protectedMetaKeys(bool $local = true): array;
    public function getLocalKeyFromMetaKey(string $meta_key): string;
}
