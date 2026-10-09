<?php

namespace Coretik\Tests\Unit\Models;

use Brain\Monkey\Actions;
use Coretik\Core\Models\Model;
use Coretik\Tests\TestCase;
use Mockery;

class HooksTest extends TestCase
{
    private function model(): Model
    {
        $model = Mockery::mock(MetableModel::class)->makePartial();
        (new \ReflectionProperty(Model::class, 'name'))->setValue($model, 'product');
        return $model;
    }

    public function testListenersRunByPriorityThenRegistrationOrder(): void
    {
        $calls = [];
        $model = $this->model()
            ->on('saved', function () use (&$calls) {
                $calls[] = 'b';
            })
            ->on('saved', function () use (&$calls) {
                $calls[] = 'a';
            }, 5)
            ->on('saved', function () use (&$calls) {
                $calls[] = 'c';
            });

        $model->trigger('saved');

        $this->assertSame(['a', 'b', 'c'], $calls);
    }

    public function testListenersReceiveCountArgs(): void
    {
        $received = [];
        $model = $this->model()
            ->on('saved', function (...$args) use (&$received) {
                $received['one'] = $args;
            })
            ->on('saved', function (...$args) use (&$received) {
                $received['two'] = $args;
            }, 10, 2);

        $model->trigger('saved', ['foo' => 'bar']);

        $this->assertSame([$model], $received['one']);
        $this->assertSame([$model, ['foo' => 'bar']], $received['two']);
    }

    public function testOff(): void
    {
        $count = 0;
        $listener = function () use (&$count) {
            $count++;
        };
        $model = $this->model()->on('saved', $listener)->on('deleted', $listener);

        $model->off('saved', $listener)->trigger('saved');
        $this->assertSame(0, $count);

        $model->trigger('deleted');
        $model->off('deleted')->trigger('deleted');
        $this->assertSame(1, $count);
    }

    public function testListenersAreNotWordPressHooks(): void
    {
        // 1.x registered a WordPress hook with a unique name per instance and event, never removed
        \Brain\Monkey\Functions\expect('add_action')->never();
        \Brain\Monkey\Functions\expect('add_filter')->never();

        $model = $this->model()->on('saved', fn () => null)->on('updated', fn () => null);
        $model->trigger('saved');

        $this->addToAssertionCount(1);
    }

    public function testEventsAreOnlyHeardByTheirInstance(): void
    {
        $heard = 0;
        $this->model()->on('saved', function () use (&$heard) {
            $heard++;
        });

        $this->model()->trigger('saved');

        $this->assertSame(0, $heard);
    }

    public function testGlobalHookIsFiredForEveryInstance(): void
    {
        $model = $this->model();
        Actions\expectDone('coretik/model/product/saved')->once()->with($model, ['foo' => 'bar']);

        $model->trigger('saved', ['foo' => 'bar']);
    }
}
