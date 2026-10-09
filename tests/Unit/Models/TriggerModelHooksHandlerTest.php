<?php

namespace Coretik\Tests\Unit\Models;

use Brain\Monkey\Actions;
use Coretik\Core\Builders\Interfaces\BuilderInterface;
use Coretik\Core\Builders\Interfaces\ModelableInterface;
use Coretik\Core\Models\Handlers\TriggerModelHooksHandler;
use Coretik\Core\Models\Model;
use Coretik\Tests\TestCase;
use Mockery;

class TriggerModelHooksHandlerTest extends TestCase
{
    private TriggerModelHooksHandler $handler;
    private $builder;
    private Model $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->model = Mockery::mock(MetableModel::class)->makePartial();
        (new \ReflectionProperty(Model::class, 'id'))->setValue($this->model, 12);
        (new \ReflectionProperty(Model::class, 'name'))->setValue($this->model, 'product');

        $this->builder = Mockery::mock(BuilderInterface::class, ModelableInterface::class);
        $this->builder->allows('getName')->andReturn('product');
        $this->builder->allows('concern')->andReturn(true);
        $this->builder->allows('model')->andReturn($this->model);

        $this->handler = new TriggerModelHooksHandler();
        $this->handler->handle($this->builder);
    }

    private function hookName(string $event): string
    {
        return (new \ReflectionMethod(Model::class, 'hookName'))->invoke($this->model, $event);
    }

    /**
     * The adapter write fires the WP hooks, as wp_update_post() does.
     */
    private function adapterFiringWpHooks(): void
    {
        $adapter = Mockery::mock();
        $adapter->allows('update')->andReturnUsing(function () {
            $post = new \WP_Post();
            $this->handler->triggerUpdated(12, $post);
            $this->handler->triggerSaved(12, $post, true);
            return 12;
        });
        (new \ReflectionProperty(Model::class, 'adapter'))->setValue($this->model, $adapter);
        $this->model->allows('changes')->andReturn([]);
    }

    public function testModelSaveTriggersEventsOnce(): void
    {
        $this->adapterFiringWpHooks();
        Actions\expectDone($this->hookName('updated'))->once();
        Actions\expectDone($this->hookName('saved'))->once();

        $this->model->save();

        $this->assertFalse(Model::isPersisting('product'));
    }

    public function testWpUpdateOutsideModelTriggersEvent(): void
    {
        // e.g. a post updated from the WP admin
        Actions\expectDone($this->hookName('updated'))->once();

        $this->handler->triggerUpdated(12, new \WP_Post());
    }

    public function testPersistingFlagIsResetOnFailure(): void
    {
        $adapter = Mockery::mock();
        $adapter->allows('update')->andThrow(new \RuntimeException('failure'));
        (new \ReflectionProperty(Model::class, 'adapter'))->setValue($this->model, $adapter);
        $this->model->allows('changes')->andReturn([]);

        try {
            $this->model->save();
            $this->fail('The adapter exception should be thrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('failure', $e->getMessage());
        }

        $this->assertFalse(Model::isPersisting('product'));
    }
}
