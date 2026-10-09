<?php

namespace Coretik\Tests\Unit\Models\Adapters;

use Brain\Monkey\Functions;
use Coretik\Core\Models\Adapters\WPCommentAdapter;
use Coretik\Core\Models\Adapters\WPPostAdapter;
use Coretik\Core\Models\Adapters\WPTermAdapter;
use Coretik\Core\Models\Adapters\WPUserAdapter;
use PHPUnit\Framework\Attributes\DataProvider;

class MetaAdaptersTest extends AdapterTestCase
{
    public static function adapters(): array
    {
        return [
            'user' => [WPUserAdapter::class, 'user'],
            'term' => [WPTermAdapter::class, 'term'],
            'comment' => [WPCommentAdapter::class, 'comment'],
            'post' => [WPPostAdapter::class, 'post'],
        ];
    }

    public static function readAdapters(): array
    {
        // WPPostAdapter::meta() keeps its own behaviour (empty string => default)
        return \array_diff_key(static::adapters(), ['post' => true]);
    }

    #[DataProvider('readAdapters')]
    public function testMetaReturnsFalsyStoredValues(string $adapter, string $type): void
    {
        Functions\expect('metadata_exists')->with($type, 12, 'count')->andReturn(true);
        Functions\expect('get_metadata')->with($type, 12, 'count', true)->andReturn('0');

        $this->assertSame('0', (new $adapter($this->model()))->meta('count', 'default'));
    }

    #[DataProvider('readAdapters')]
    public function testMetaReturnsDefaultWhenMissing(string $adapter, string $type): void
    {
        Functions\expect('metadata_exists')->with($type, 12, 'count')->andReturn(false);
        Functions\expect('get_metadata')->never();

        $this->assertSame('default', (new $adapter($this->model()))->meta('count', 'default'));
    }

    #[DataProvider('adapters')]
    public function testUpdateMetaUpdatesExistingFalsyMeta(string $adapter, string $type): void
    {
        Functions\when('metadata_exists')->justReturn(true);
        Functions\when('get_metadata')->justReturn('0');
        Functions\expect("update_{$type}_meta")->once()->with(12, 'count', 1)->andReturn(true);
        Functions\expect("add_{$type}_meta")->never();

        (new $adapter($this->model()))->updateMeta('count', 1);
    }

    #[DataProvider('adapters')]
    public function testUpdateMetaAddsMissingMeta(string $adapter, string $type): void
    {
        Functions\when('metadata_exists')->justReturn(false);
        Functions\expect("update_{$type}_meta")->never();
        Functions\expect("add_{$type}_meta")->once()->with(12, 'count', 1, false)->andReturn(5);

        (new $adapter($this->model()))->updateMeta('count', 1);
    }

    #[DataProvider('adapters')]
    public function testUpdateMetaWithUnchangedValueDoesNotThrow(string $adapter, string $type): void
    {
        Functions\when('metadata_exists')->justReturn(true);
        Functions\when('get_metadata')->justReturn('42');
        // WP returns false when the value is unchanged
        Functions\expect("update_{$type}_meta")->once()->andReturn(false);

        (new $adapter($this->model()))->updateMeta('count', 42);
        $this->addToAssertionCount(1);
    }

    #[DataProvider('adapters')]
    public function testUpdateMetaWithUnchangedArrayDoesNotThrow(string $adapter, string $type): void
    {
        Functions\when('metadata_exists')->justReturn(true);
        Functions\when('get_metadata')->justReturn(['a' => 1]);
        Functions\expect("update_{$type}_meta")->once()->andReturn(false);

        (new $adapter($this->model()))->updateMeta('list', ['a' => 1]);
        $this->addToAssertionCount(1);
    }

    #[DataProvider('adapters')]
    public function testUpdateMetaFailureThrows(string $adapter, string $type): void
    {
        Functions\when('metadata_exists')->justReturn(true);
        Functions\when('get_metadata')->justReturn('old');
        Functions\expect("update_{$type}_meta")->once()->andReturn(false);

        $this->expectException(\RuntimeException::class);
        (new $adapter($this->model()))->updateMeta('count', 'new');
    }

    #[DataProvider('adapters')]
    public function testAddMetaFailureThrows(string $adapter, string $type): void
    {
        Functions\when('metadata_exists')->justReturn(false);
        Functions\expect("add_{$type}_meta")->once()->andReturn(false);

        $this->expectException(\RuntimeException::class);
        (new $adapter($this->model()))->updateMeta('count', 1);
    }

    public function testTermMetaWpErrorThrows(): void
    {
        Functions\when('metadata_exists')->justReturn(false);
        Functions\expect('add_term_meta')->once()->andReturn(new \WP_Error('shared_term', 'Shared term'));

        $this->expectExceptionMessage('Shared term');
        (new WPTermAdapter($this->model()))->updateMeta('count', 1);
    }
}
