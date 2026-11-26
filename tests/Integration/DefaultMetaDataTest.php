<?php

namespace Coretik\Tests\Integration;

use Coretik\Core\Models\Handlers\DefaultMetaDataHandler;

/**
 * Default meta values (MetaDefinition::setDefaultValue()): served for the objects of the builder only, through the
 * filter of its meta type
 */
class DefaultMetaDataTest extends IntegrationTestCase
{
    /**
     * The users builder handler is attached once by the first TutorModel built (Metable): its hook is removed with the
     * other hooks after each test, attached again here
     */
    public function set_up(): void
    {
        parent::set_up();
        (new DefaultMetaDataHandler())->handle(coretik()->schema('users', 'user'));
    }

    public function testHookOfTheBuilderMetaType(): void
    {
        $handler = fn (string ...$path) => tap(new DefaultMetaDataHandler(), fn ($handler) => (new \ReflectionProperty($handler, 'builder'))->setValue($handler, coretik()->schema(...$path)));

        $this->assertSame('default_post_metadata', $handler('product')->hook());
        $this->assertSame('default_user_metadata', $handler('users', 'user')->hook());
    }

    public function testDefaultValueOfAUserMeta(): void
    {
        $user = self::factory()->user->create();
        coretik()->schema('users', 'user')->model($user);

        $this->assertSame('Anonyme', get_user_meta($user, 'coretik_nickname', true));
        $this->assertSame(['Anonyme'], get_user_meta($user, 'coretik_nickname', false));
    }

    /**
     * A user builder with default values does not look for a user when a post meta is missing
     */
    public function testPostMetaReadsDoNotQueryUsers(): void
    {
        global $wpdb;

        $user = self::factory()->user->create();
        coretik()->schema('users', 'user')->model($user);

        $post = self::factory()->post->create(['post_type' => 'product']);
        // An id no user has: get_user_by() would query it on each read
        $missingUser = (int)$wpdb->get_var("SELECT MAX(ID) FROM {$wpdb->users}") + 1000;
        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->posts} SET ID = %d WHERE ID = %d", $missingUser, $post));
        clean_post_cache($post);
        update_meta_cache('post', [$missingUser]);

        $before = $wpdb->num_queries;
        foreach (range(1, 5) as $i) {
            get_post_meta($missingUser, 'missing_meta', true);
        }

        $this->assertSame(0, $wpdb->num_queries - $before);
    }
}
