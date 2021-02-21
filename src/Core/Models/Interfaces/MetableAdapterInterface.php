<?php

namespace Coretik\Core\Models\Interfaces;

interface MetableAdapterInterface extends AdapterInterface
{
    public function meta(string $key, $default, bool $single);
    public function updateMeta(string $key, $value, bool $unique = false);
    public function deleteMeta(string $key, $value = '');
}
