<?php

namespace Coretik\Tests\Unit\Models\Adapters;

use Coretik\Core\Models\Interfaces\ModelInterface;
use Coretik\Tests\TestCase;
use Mockery;

abstract class AdapterTestCase extends TestCase
{
    protected function model(int $id = 12): ModelInterface
    {
        $model = Mockery::mock(ModelInterface::class);
        $model->allows('id')->andReturn($id);
        return $model;
    }
}
