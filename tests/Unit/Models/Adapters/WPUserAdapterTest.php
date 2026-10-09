<?php

namespace Coretik\Tests\Unit\Models\Adapters;

use Brain\Monkey\Functions;
use Coretik\Core\Models\Adapters\WPUserAdapter;

class WPUserAdapterTest extends AdapterTestCase
{
    public function testCreateReturnsUserId(): void
    {
        Functions\expect('wp_insert_user')->once()->andReturn(34);

        $this->assertSame(34, (new WPUserAdapter($this->model(0)))->create(['user_login' => 'john']));
    }

    public function testCreateThrowsOnWpError(): void
    {
        Functions\expect('wp_insert_user')->once()->andReturn(new \WP_Error('existing_user_login', 'Login already exists'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Login already exists');
        (new WPUserAdapter($this->model(0)))->create(['user_login' => 'john']);
    }

    public function testUpdateTargetsModelId(): void
    {
        Functions\expect('wp_update_user')->once()->with(['display_name' => 'John', 'ID' => 12])->andReturn(12);

        $this->assertSame(12, (new WPUserAdapter($this->model()))->update(['display_name' => 'John']));
    }

    public function testUpdateThrowsOnWpError(): void
    {
        Functions\expect('wp_update_user')->once()->andReturn(new \WP_Error('invalid_user_id', 'Invalid user ID.'));

        $this->expectException(\RuntimeException::class);
        (new WPUserAdapter($this->model()))->update([]);
    }
}
