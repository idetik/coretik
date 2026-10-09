<?php

namespace Coretik\Tests\Integration;

use Coretik\Core\Query\Post;
use Coretik\Tests\Integration\Fixtures\WebPostQuery;

class QueryTest extends IntegrationTestCase
{
    public function set_up(): void
    {
        parent::set_up();
        app()->schema('product')->querier(WebPostQuery::class);
        self::factory()->post->create_many(3, ['post_type' => 'product', 'post_status' => 'publish']);
        self::factory()->post->create(['post_type' => 'product', 'post_status' => 'draft']);
        self::factory()->post->create(['post_type' => 'product', 'post_status' => 'private']);
    }

    public function tear_down(): void
    {
        app()->schema('product')->querier(Post::class);
        remove_all_filters('wp_doing_ajax');
        parent::tear_down();
    }

    public function testFrontendQueriesPublishedProducts(): void
    {
        $this->assertSame(3, app()->schema('product')->query()->all()->count());
    }

    public function testAjaxQueriesPublishedProductsForVisitors(): void
    {
        add_filter('wp_doing_ajax', '__return_true');
        wp_set_current_user(0);

        $this->assertSame(3, app()->schema('product')->query()->all()->count());
    }

    public function testAjaxQueriesAllStatusesForEditors(): void
    {
        add_filter('wp_doing_ajax', '__return_true');
        wp_set_current_user(self::factory()->user->create(['role' => 'editor']));

        $this->assertSame(5, app()->schema('product')->query()->all()->count());
    }

    public function testWhereSetsQueryParameter(): void
    {
        $parent = self::factory()->post->create(['post_type' => 'product', 'post_status' => 'publish']);
        $child = self::factory()->post->create(['post_type' => 'product', 'post_status' => 'publish', 'post_parent' => $parent]);

        $ids = app()->schema('product')->query()->where('post_parent', $parent)->ids();

        $this->assertSame([$child], \array_map('intval', $ids));
    }

    public function testWhereMeta(): void
    {
        $id = self::factory()->post->create(['post_type' => 'product', 'post_status' => 'publish', 'meta_input' => ['price' => '99']]);

        $ids = app()->schema('product')->query()->whereMeta('price', '99')->ids();

        $this->assertSame([$id], \array_map('intval', $ids));
    }

    public function testUnknownWhereKeyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app()->schema('product')->query()->where('price', '99');
    }

    public function testTotalCountsAllPages(): void
    {
        $query = app()->schema('product')->query()->limit(2);

        $this->assertSame(2, $query->count());
        $this->assertSame(3, $query->total());
    }
}
