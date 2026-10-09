<?php

namespace Coretik\Tests\Unit\Query;

use Brain\Monkey\Functions;
use Coretik\Core\Builders\Interfaces\ModelableInterface;
use Coretik\Core\Query\Post;
use Coretik\Tests\TestCase;
use Mockery;

class PostDefaultStatusTest extends TestCase
{
    private function defaultStatus(bool $ajax = false, bool $admin = false, bool $cron = false, bool $cli = false, bool $canEdit = false)
    {
        Functions\when('wp_doing_ajax')->justReturn($ajax);
        // admin-ajax.php is an admin request too
        Functions\when('is_admin')->justReturn($admin || $ajax);
        Functions\when('wp_doing_cron')->justReturn($cron);
        Functions\when('get_post_type_object')->justReturn((object)['cap' => (object)['edit_posts' => 'edit_products']]);
        Functions\expect('current_user_can')->andReturnUsing(fn ($cap) => $canEdit && 'edit_products' === $cap);

        $mediator = Mockery::mock(ModelableInterface::class);
        $mediator->allows('getName')->andReturn('product');

        $query = new class ($mediator, $cli) extends Post {
            public function __construct($mediator, private bool $cli)
            {
                parent::__construct($mediator);
            }

            protected function isCli(): bool
            {
                return $this->cli;
            }
        };

        return $query->builder()->get('post_status');
    }

    public function testFrontendQueriesPublishedPosts(): void
    {
        $this->assertSame('publish', $this->defaultStatus());
    }

    public function testAjaxQueriesPublishedPostsForVisitors(): void
    {
        $this->assertSame('publish', $this->defaultStatus(ajax: true));
    }

    public function testAjaxQueriesAllStatusesForEditors(): void
    {
        $status = $this->defaultStatus(ajax: true, canEdit: true);

        $this->assertContains('draft', $status);
        $this->assertContains('private', $status);
    }

    public function testAdminCronAndCliQueryAllStatuses(): void
    {
        $this->assertContains('draft', $this->defaultStatus(admin: true));
        $this->assertContains('draft', $this->defaultStatus(cron: true));
        $this->assertContains('draft', $this->defaultStatus(cli: true));
    }

    public function testDefaultStatusIsFilterable(): void
    {
        \Brain\Monkey\Filters\expectApplied('coretik/query/post/default_status')
            ->once()
            ->with('publish', 'product')
            ->andReturn(['publish', 'private']);

        $this->assertSame(['publish', 'private'], $this->defaultStatus(ajax: true));
    }
}
