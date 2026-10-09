<?php

namespace Coretik\Tests\Unit;

use Coretik\App;
use Coretik\Core\Builders\BuilderModelable;
use Coretik\Core\Builders\Interfaces\ModelableInterface;
use Coretik\Core\Container;
use Coretik\Core\Query\Post;
use Coretik\Core\Schema;
use Coretik\Core\Exception\ContainerValueNotFoundException;
use Coretik\Tests\TestCase;
use Brain\Monkey\Functions;
use Mockery;

class MagicCallsTest extends TestCase
{
    private function app(array $services = []): App
    {
        $app = (new \ReflectionClass(App::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(App::class, 'container'))->setValue($app, new Container($services));
        return $app;
    }

    private function query(): Post
    {
        Functions\when('wp_doing_ajax')->justReturn(false);
        Functions\when('is_admin')->justReturn(false);
        Functions\when('wp_doing_cron')->justReturn(false);

        $mediator = Mockery::mock(ModelableInterface::class);
        $mediator->allows('getName')->andReturn('product');
        return new Post($mediator);
    }

    public function testAppCallsContainerServices(): void
    {
        $app = $this->app(['greeting' => fn () => fn ($name) => 'Hello ' . $name]);

        $this->assertSame('Hello John', $app->greeting('John'));
    }

    public function testAppUnknownMethodThrows(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('no "shcema" service');
        $this->app()->shcema();
    }

    public function testBuilderUnknownMethodThrows(): void
    {
        $builder = new class extends BuilderModelable {
            public function getType(): string
            {
                return 'post';
            }

            public function getName(): string
            {
                return 'product';
            }

            public function wpObject(int $id)
            {
                return null;
            }

            public function concern(int $objectId): bool
            {
                return false;
            }
        };
        $builder->attach('hello', fn ($name) => 'Hello ' . $name);

        $this->assertSame('Hello John', $builder->hello('John'));
        $this->expectException(\BadMethodCallException::class);
        $builder->helo('John');
    }

    public function testQueryExplicitMethodsAreChainable(): void
    {
        $query = $this->query()->set('post_parent', 5)->limit(3)->childOf(5)->notIn([1, 2]);

        $this->assertSame(3, $query->builder()->get('posts_per_page'));
        $this->assertSame([1, 2], $query->builder()->get('post__not_in'));
    }

    public function testQueryForwardsBuilderSpecificMethods(): void
    {
        $query = $this->query()->page(2);

        $this->assertSame(2, $query->builder()->get('paged'));
    }

    public function testQueryUnknownMethodThrows(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->query()->limt(3);
    }

    public function testSchemaGetReturnsNullForUnknownType(): void
    {
        $schema = (new \ReflectionClass(Schema::class))->newInstanceWithoutConstructor();

        $this->assertNull($schema->get('missing', 'unknown-type'));
        $this->assertCount(0, $schema->type('unknown-type'));
    }

    public function testSchemaModelableThrowsWhenMissing(): void
    {
        $schema = (new \ReflectionClass(Schema::class))->newInstanceWithoutConstructor();

        $this->expectException(ContainerValueNotFoundException::class);
        $schema->modelable('missing', 'post');
    }
}
