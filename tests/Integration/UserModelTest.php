<?php

namespace Coretik\Tests\Integration;

class UserModelTest extends IntegrationTestCase
{
    public function testUpdateExistingUser(): void
    {
        $id = self::factory()->user->create(['display_name' => 'John']);
        $user = app()->schema('users', 'user')->model($id);

        $user->display_name = 'Jane';
        $user->save();

        $this->assertSame('Jane', get_userdata($id)->display_name);
    }

    public function testCreateWithExistingLoginThrows(): void
    {
        self::factory()->user->create(['user_login' => 'john']);
        $user = app()->schema('users', 'user')->model();
        $user->user_login = 'john';
        $user->user_pass = 'secret';
        $user->user_email = 'other@example.org';

        $this->expectException(\RuntimeException::class);
        $user->save();
    }
}
