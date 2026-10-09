<?php

namespace Coretik\Tests\Unit\Models;

use Coretik\Core\Builders\BuilderModelable;
use Coretik\Core\Builders\Interfaces\BuilderInterface;
use Coretik\Core\Builders\Interfaces\ModelableInterface;
use Coretik\Core\Models\Handlers\Guard;
use Coretik\Core\Schema;
use Coretik\Tests\TestCase;
use Mockery;

class GuardTest extends TestCase
{
    private function builder(string $type = 'post', array $protectedKeys = ['_secret'], ?bool $concern = true, bool $protected = true)
    {
        $model = Mockery::mock(MetableModel::class);
        $model->allows('protectedMetaKeys')->with(false)->andReturn($protectedKeys);
        $model->allows('isProtectedMeta')->andReturn($protected);

        $builder = Mockery::mock(BuilderInterface::class, ModelableInterface::class);
        $builder->allows('getType')->andReturn($type);
        $builder->allows('concern')->andReturn($concern);
        $builder->allows('model')->andReturn($model);
        return $builder;
    }

    private function guard($builder): Guard
    {
        $guard = new Guard();
        $guard->handle($builder);
        return $guard;
    }

    public function testPostBuilderGuardsPostMetadataOnly(): void
    {
        $guard = $this->guard($this->builder('post'));

        foreach (['add', 'update', 'delete'] as $action) {
            $this->assertNotFalse(\has_filter("{$action}_post_metadata", [$guard, 'guard']), $action);
            $this->assertFalse(\has_filter("{$action}_user_metadata", [$guard, 'guard']), $action);
        }
    }

    public function testTaxonomyBuilderGuardsTermMetadata(): void
    {
        $guard = $this->guard($this->builder('taxonomy'));

        $this->assertNotFalse(\has_filter('update_term_metadata', [$guard, 'guard']));
        $this->assertFalse(\has_filter('update_post_metadata', [$guard, 'guard']));
    }

    public function testFreezeRemovesFilters(): void
    {
        $guard = $this->guard($this->builder('comment'));
        $guard->freeze();

        $this->assertFalse(\has_filter('update_comment_metadata', [$guard, 'guard']));
    }

    public function testProtectedMetaWriteIsShortCircuited(): void
    {
        $this->assertTrue($this->guard($this->builder())->guard(null, 12, '_secret', 'x'));
    }

    public function testUnprotectedKeyKeepsPreviousCheckWithoutLoadingModel(): void
    {
        $builder = $this->builder();
        $builder->expects('model')->with(12)->never();

        $this->assertFalse($this->guard($builder)->guard(false, 12, 'title', 'x'));
        $this->assertNull($this->guard($builder)->guard(null, 12, 'title', 'x'));
    }

    public function testObjectOfAnotherBuilderIsNotGuarded(): void
    {
        $this->assertNull($this->guard($this->builder(concern: false))->guard(null, 12, '_secret', 'x'));
    }

    public function testRuleAllowingWriteForThisModel(): void
    {
        // protectWith() rule: the meta is protected for some models only
        $this->assertNull($this->guard($this->builder(protected: false))->guard(null, 12, '_secret', 'x'));
    }

    public function testSchemaAttachesGuardToModelableBuilders(): void
    {
        // Built-in builders need WordPress: register a test builder on a schema without its constructor
        $schema = (new \ReflectionClass(Schema::class))->newInstanceWithoutConstructor();
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

        $schema->register($builder);

        $this->assertTrue($builder->hasHandlerClassName(Guard::class));
        $this->assertNotFalse(\has_filter('update_post_metadata'));
    }
}
