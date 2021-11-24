<?php

namespace Coretik\Core\Models;

interface ModelsInterface
{
    public function get(int $id);
    public function has(int $id): bool;
}
