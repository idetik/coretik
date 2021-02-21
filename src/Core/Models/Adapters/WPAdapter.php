<?php

namespace Coretik\Core\Models\Adapters;

use Coretik\Core\Models\Interfaces\ModelInterface;

abstract class WPAdapter
{
    protected $model;

    public function __construct(ModelInterface $model)
    {
        $this->model = $model;
    }
}
