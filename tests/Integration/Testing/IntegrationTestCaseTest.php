<?php

namespace Coretik\Tests\Integration\Testing;

use Coretik\App;
use Coretik\Services\Queue\DatabaseStore;
use Coretik\Services\Queue\Queue;
use Coretik\Tests\Integration\Fixtures\CountingJob;
use Coretik\Tests\Integration\IntegrationTestCase;
use PHPUnit\Framework\AssertionFailedError;

/**
 * Coretik\Testing\IntegrationTestCase: state reset before each test, helpers
 */
class IntegrationTestCaseTest extends IntegrationTestCase
{
    public function testNewRequestForgetsTheModelsKeptInMemory(): void
    {
        $id = self::factory()->post->create(['post_type' => 'product']);
        $product = coretik()->schema('product')->model($id);
        $this->assertSame($product, coretik()->schema('product')->model($id), 'Kept in memory');

        $this->newRequest();

        $this->assertNotSame($product, coretik()->schema('product')->model($id));
    }

    public function testFreshLoadsTheModelAgain(): void
    {
        $product = coretik()->schema('product')->model();
        $product->post_title = 'Tent';
        $product->price = '10';
        $product = $product->save();
        \update_post_meta($product->id(), 'price', '12');

        $fresh = $this->fresh($product);

        $this->assertNotSame($product, $fresh);
        $this->assertSame($product->id(), $fresh->id());
        $this->assertSame('12', $fresh->meta('price'));
    }

    public function testRolesOfTheSchemaAreSavedBeforeEachTest(): void
    {
        $this->assertNotNull(\get_role('coach'), 'Saved by set_up()');

        \remove_role('coach');
        $this->persistRoles();

        $this->assertNotNull(\get_role('coach'));
        $this->assertSame('Coach', \wp_roles()->get_names()['coach']);
    }

    public function testLoggedInUsers(): void
    {
        $admin = $this->admin(['user_email' => 'boss@example.org']);
        $this->assertSame($admin->ID, \get_current_user_id());
        $this->assertSame('boss@example.org', $admin->user_email);
        $this->assertTrue(\current_user_can('manage_options'));

        $subscriber = $this->subscriber();
        $this->assertSame($subscriber->ID, \get_current_user_id());
        $this->assertFalse(\current_user_can('edit_posts'));

        $coach = $this->loginAs('coach');
        $this->assertSame(['coach'], $coach->roles);

        $this->logout();
        $this->assertFalse(\is_user_logged_in());
    }

    public function testEmailsAreCaught(): void
    {
        $this->assertSame([], $this->sentEmails(), 'Reset before each test');

        \wp_mail('first@example.org', 'First', 'Hello');
        \wp_mail('second@example.org', 'Second', 'Bye');

        $this->assertCount(2, $this->sentEmails());
        $this->assertSame('Second', $this->lastEmail()['subject']);
        $this->assertSame('second@example.org', $this->lastEmail()['to'][0][0]);
    }

    public function testLastEmailFailsWhenNoneSent(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('No email sent');

        $this->lastEmail();
    }

    public function testWorkQueueRunsTheJobsDue(): void
    {
        $container = (new \ReflectionProperty(App::class, 'container'))->getValue(App::instance());
        $this->assertFalse($container->has('queue'), 'Opt-in service');
        $container['queue'] = fn () => new Queue(new DatabaseStore());

        try {
            coretik()->queue()->push(new CountingJob('kit'));

            $this->assertSame(1, $this->workQueue());
            $this->assertSame(1, (int)\get_option('counted_kit'));
        } finally {
            unset($container['queue']);
        }
    }
}
