<?php

namespace Coretik\Core\Models\Interfaces;

interface CRUDInterface
{
    public function create();
    public function get();
    public function update();
    public function delete();
}
