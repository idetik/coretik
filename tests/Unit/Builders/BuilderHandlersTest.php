<?php

namespace Coretik\Tests\Unit\Builders;

use Coretik\Core\Builders\Builder;
use Coretik\Core\Builders\Handler;
use Coretik\Tests\TestCase;

class BuilderHandlersTest extends TestCase
{
    private function builder(): Builder
    {
        return new class () extends Builder {
            public function getName(): string
            {
                return 'product';
            }

            public function getType(): string
            {
                return 'post';
            }
        };
    }

    private function handler(string $name): Handler
    {
        return new class ($name) extends Handler {
            public function __construct(public string $name)
            {
            }

            public function actions(): void
            {
            }

            public function freeze(): void
            {
            }
        };
    }

    private function otherHandler(): Handler
    {
        return new class () extends Handler {
            public function actions(): void
            {
            }

            public function freeze(): void
            {
            }
        };
    }

    public function testHandlersOfAClassInTheDeclarationOrder(): void
    {
        $first = $this->handler('first');
        $second = $this->handler('second');
        $other = $this->otherHandler();
        $builder = $this->builder()->handler($first)->handler($other)->handler($second);

        $this->assertSame([$first, $second], $builder->getHandlersOf($first::class));
        $this->assertSame([$first, $other, $second], $builder->getHandlersOf(Handler::class));
        $this->assertSame([$other], $builder->getHandlersOf($other::class));
    }

    public function testFirstHandlerAgreedByTheFilter(): void
    {
        $first = $this->handler('first');
        $second = $this->handler('second');
        $builder = $this->builder()->handler($first)->handler($second);

        $this->assertSame($first, $builder->getHandler($first::class));
        $this->assertSame($second, $builder->getHandler($first::class, fn ($handler) => 'second' === $handler->name));
        $this->assertSame([$second], $builder->getHandlersOf($first::class, fn ($handler) => 'second' === $handler->name));
        $this->assertNull($builder->getHandler($first::class, fn () => false));
        $this->assertNull($builder->getHandler($this->otherHandler()::class));
    }
}

