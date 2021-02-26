<?php

namespace Coretik\Services\Notices;

interface StorageInterface
{
    public function get(): \ArrayIterator;
    public function set(\ArrayIterator $iterator);
}
