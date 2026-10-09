<?php

namespace Coretik\Tests\Unit\Builders;

use Coretik\Core\Builders\BuilderModelable;
use Coretik\Tests\TestCase;

class BuilderModelableTest extends TestCase
{
    private function builder(string $type, string $name): BuilderModelable
    {
        return new class ($type, $name) extends BuilderModelable {
            public function __construct(private string $type, private string $name)
            {
                parent::__construct();
            }

            public function getType(): string
            {
                return $this->type;
            }

            public function getName(): string
            {
                return $this->name;
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
    }

    public function testBuildersOfSameTypeDoNotShareModels(): void
    {
        $page = $this->builder('post', 'page');
        $product = $this->builder('post', 'product');

        $this->assertNotSame($page->models(), $product->models());
    }

    public function testBuildersWithSameNameAndTypeShareModels(): void
    {
        $this->assertSame(
            $this->builder('post', 'event')->models(),
            $this->builder('post', 'event')->models()
        );
    }

    public function testBuildersWithSameNameAndDifferentTypeDoNotShareModels(): void
    {
        $this->assertNotSame(
            $this->builder('post', 'category')->models(),
            $this->builder('taxonomy', 'category')->models()
        );
    }
}
