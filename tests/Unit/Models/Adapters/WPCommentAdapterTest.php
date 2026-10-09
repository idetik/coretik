<?php

namespace Coretik\Tests\Unit\Models\Adapters;

use Brain\Monkey\Functions;
use Coretik\Core\Models\Adapters\WPCommentAdapter;

class WPCommentAdapterTest extends AdapterTestCase
{
    public function testCreateThrowsOnFailure(): void
    {
        Functions\expect('wp_insert_comment')->once()->andReturn(false);

        $this->expectException(\RuntimeException::class);
        (new WPCommentAdapter($this->model(0)))->create([]);
    }

    public function testUpdateTargetsModelId(): void
    {
        Functions\expect('wp_update_comment')->once()->with(['comment_content' => 'Hi', 'comment_ID' => 12])->andReturn(1);

        $this->assertSame(12, (new WPCommentAdapter($this->model()))->update(['comment_content' => 'Hi']));
    }

    public function testUpdateWithoutChangesDoesNotThrow(): void
    {
        // wp_update_comment returns 0 when no row was updated
        Functions\expect('wp_update_comment')->once()->andReturn(0);

        $this->assertSame(12, (new WPCommentAdapter($this->model()))->update([]));
    }

    public function testUpdateThrowsOnFailure(): void
    {
        Functions\expect('wp_update_comment')->once()->andReturn(false);

        $this->expectException(\RuntimeException::class);
        (new WPCommentAdapter($this->model()))->update([]);
    }

    public function testGetFailureMessageContainsCommentId(): void
    {
        Functions\expect('get_comment')->once()->andReturn(null);

        $this->expectExceptionMessage('Get comment: failure - 99');
        (new WPCommentAdapter($this->model()))->get(99);
    }
}
