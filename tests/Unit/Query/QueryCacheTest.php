<?php

namespace Coretik\Tests\Unit\Query;

use Coretik\Core\Query\QueryCache;
use Coretik\Tests\TestCase;

class QueryCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new \ReflectionProperty(QueryCache::class, 'instance'))->setValue(null, null);
    }

    public function testFlushedOnWrites(): void
    {
        $cache = QueryCache::instance();

        $this->assertNotFalse(\has_action('save_post', [$cache, 'flush']));
        $this->assertNotFalse(\has_action('updated_post_meta', [$cache, 'flush']));

        $cache->set('a', 1);
        $cache->flush();
        $this->assertFalse($cache->has('a'));
    }

    public function testOldestEntriesAreRemovedOverTheLimit(): void
    {
        \Brain\Monkey\Filters\expectApplied('coretik/query/cache/max_entries')->andReturn(2);
        $cache = QueryCache::instance();

        $cache->set('a', 1);
        $cache->set('b', 2);
        $cache->set('a', 3); // refreshed: now the newest
        $cache->set('c', 4);

        $this->assertSame(2, $cache->count());
        $this->assertFalse($cache->has('b'));
        $this->assertSame(3, $cache->get('a'));
    }
}
