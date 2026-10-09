<?php

namespace Coretik\Tests\Unit\Query;

use Coretik\Core\Builders\Interfaces\ModelableInterface;
use Coretik\Core\Query\Post;
use Coretik\Core\Query\Query;
use Coretik\Core\Query\User;
use Coretik\Tests\TestCase;
use Mockery;

class TotalTest extends TestCase
{
    /**
     * A querier whose WP query already ran, with the given result.
     */
    private function querier(string $class, object $wpQuery): Query
    {
        $querier = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(Query::class, 'query'))->setValue($querier, $wpQuery);
        return $querier;
    }

    public function testPostTotalCountsAllPages(): void
    {
        $querier = $this->querier(Post::class, (object)['posts' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10], 'found_posts' => 42]);

        $this->assertSame(10, $querier->count());
        $this->assertSame(42, $querier->total());
    }

    public function testPostTotalWithoutFoundRows(): void
    {
        $querier = $this->querier(Post::class, (object)['posts' => [1, 2, 3], 'found_posts' => 0]);

        $this->assertSame(3, $querier->total());
    }

    public function testUserTotal(): void
    {
        $wpQuery = Mockery::mock();
        $wpQuery->results = [1, 2];
        $wpQuery->allows('get_total')->andReturn(25);

        $this->assertSame(25, $this->querier(User::class, $wpQuery)->total());
    }
}
