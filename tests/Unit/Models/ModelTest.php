<?php

namespace Coretik\Tests\Unit\Models;

use Coretik\Core\Models\Model;
use Coretik\Tests\TestCase;
use Mockery;

class ModelTest extends TestCase
{
    private function model(int $id): Model
    {
        $model = Mockery::mock(MetableModel::class)->makePartial();
        (new \ReflectionProperty(Model::class, 'id'))->setValue($model, $id);
        // Fails the test if the adapter is used
        (new \ReflectionProperty(Model::class, 'adapter'))->setValue($model, Mockery::mock());
        return $model;
    }

    public function testCreateExistingModelIsIgnored(): void
    {
        $model = $this->model(12);

        $this->assertSame($model, $model->create());
    }

    public function testUpdateNewModelIsIgnored(): void
    {
        $model = $this->model(0);
        $update = new \ReflectionMethod(Model::class, 'update');

        $this->assertSame($model, $update->invoke($model));
    }
}
