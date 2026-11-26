<?php

namespace Coretik\Tests\Unit\Builders\Users;

use Brain\Monkey\Functions;
use Coretik\App;
use Coretik\Core\Builders\UserType;
use Coretik\Core\Builders\Users\CacheBuster;
use Coretik\Core\Builders\Users\Registry;
use Coretik\Core\Container;
use Coretik\Tests\TestCase;
use Mockery;

class RegistryTest extends TestCase
{
    /** @var array<string, mixed> In memory wp_options */
    private array $options = [];

    /** @var string[] Roles passed to remove_role() */
    private array $removedRoles = [];

    private $notices;

    private array $postTypes = ['post' => 'post', 'page' => 'page'];

    private array $taxonomies = ['category' => 'category'];

    /** Calls to add_role() through Registry::sync() */
    private int $rolesSaved = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->options = [];
        $this->removedRoles = [];
        $this->rolesSaved = 0;

        Functions\when('get_option')->alias(fn ($key, $default = false) => $this->options[$key] ?? $default);
        // Like WordPress: add_option() does nothing when the option already exists, even with an empty value
        Functions\when('add_option')->alias(function ($key, $value) {
            if (\array_key_exists($key, $this->options)) {
                return false;
            }
            $this->options[$key] = $value;
            return true;
        });
        Functions\when('update_option')->alias(function ($key, $value) {
            $this->options[$key] = $value;
        });
        Functions\when('get_post_types')->alias(fn () => $this->postTypes);
        Functions\when('get_taxonomies')->alias(fn () => $this->taxonomies);
        Functions\when('get_role')->alias(fn ($name) => (object)['name' => $name, 'capabilities' => []]);
        Functions\when('remove_role')->alias(function ($name) {
            $this->removedRoles[] = $name;
        });

        $this->notices = Mockery::mock();
        $this->notices->allows('success');
        $this->installApp(['notices' => fn () => $this->notices]);
        $this->resetRegistry();
    }

    protected function tearDown(): void
    {
        $this->resetRegistry();
        $this->setStatic(App::class, 'instance', null);
        parent::tearDown();
    }

    private function installApp(array $services): void
    {
        $app = (new \ReflectionClass(App::class))->newInstanceWithoutConstructor();
        $property = new \ReflectionProperty(App::class, 'container');
        $property->setValue($app, new Container($services));
        $this->setStatic(App::class, 'instance', $app);
    }

    private function resetRegistry(): void
    {
        $this->setStatic(Registry::class, 'instance', null);
    }

    private function setStatic(string $class, string $property, $value): void
    {
        (new \ReflectionProperty($class, $property))->setValue(null, $value);
    }

    /**
     * Simulate an admin request: user types are attached to a fresh registry, then saved.
     */
    private function request(UserType ...$types): Registry
    {
        $this->resetRegistry();
        $registry = Registry::instance();
        foreach ($types as $type) {
            $registry->attach($type);
        }
        $registry->save();
        return $registry;
    }

    public function testChangingRoleCapsDoesNotRemoveRole(): void
    {
        $this->request(new UserType('manager', 'Manager', ['custom' => ['read']]));
        $this->request(new UserType('manager', 'Manager', ['custom' => ['read', 'edit_posts']]));

        $this->assertSame([], $this->removedRoles);
    }

    public function testRemovedUserTypeRemovesRole(): void
    {
        $this->request(new UserType('manager', 'Manager'), new UserType('editor_custom', 'Editor'));
        $this->request(new UserType('manager', 'Manager'));

        $this->assertSame(['editor_custom'], $this->removedRoles);
    }

    public function testAdministratorRoleIsNeverRemoved(): void
    {
        $this->request(new UserType('administrator', 'Administrator', ['allow_all']));
        $this->request();

        $this->assertSame([], $this->removedRoles);
    }

    public function testRolesAreStoredAsArrays(): void
    {
        $this->request(new UserType('manager', 'Manager', ['custom' => ['read']]));

        $this->assertSame(
            ['manager' => ['label' => 'Manager', 'caps' => ['custom' => ['read']]]],
            $this->options[Registry::OPTION_KEY]
        );
    }

    public function testLegacySerializedRegistryIsMigrated(): void
    {
        // Before 1.13.7 the registry stored itself (serialized UserType objects)
        $legacy = new Registry();
        $legacy->attach(new UserType('manager', 'Manager'));
        $legacy->attach(new UserType('old_role', 'Old'));
        $this->options[Registry::OPTION_KEY] = \unserialize(\serialize($legacy));

        $this->request(new UserType('manager', 'Manager'));

        $this->assertSame(['old_role'], $this->removedRoles);
        $this->assertSame(['manager'], \array_keys($this->options[Registry::OPTION_KEY]));
    }

    public function testUnreadableStoredRegistryIsIgnored(): void
    {
        $this->options[Registry::OPTION_KEY] = \unserialize('O:23:"App\\Removed\\OldRegistry":0:{}');

        $this->request(new UserType('manager', 'Manager'));

        $this->assertSame([], $this->removedRoles);
    }

    public function testHasDiffAfterSave(): void
    {
        $registry = $this->request(new UserType('manager', 'Manager'));

        $this->assertFalse($registry->hasDiff());
        $registry->attach(new UserType('other', 'Other'));
        $this->assertTrue($registry->hasDiff());
    }

    private function flushRequest(bool $admin = true, string $nonce = ''): void
    {
        $_GET = [Registry::QUERY_VAR_FLUSH => '1', '_wpnonce' => $nonce];
        Functions\when('is_admin')->justReturn(true);
        Functions\when('current_user_can')->justReturn($admin);
        Functions\when('wp_verify_nonce')->alias(fn ($nonce, $action) => 'valid' === $nonce && Registry::QUERY_VAR_FLUSH === $action ? 1 : false);
        Functions\when('admin_url')->justReturn('https://example.com/wp-admin/');
        Functions\when('add_query_arg')->alias(fn ($key, $value, $url) => $url . '?' . $key . '=' . $value);
        Functions\when('wp_nonce_url')->alias(fn ($url) => $url . '&amp;_wpnonce=valid');
        Functions\when('esc_url')->returnArg();

        $this->options[CacheBuster::CACHE_KEY] = 'previous-hash';
        Registry::triggerFlush();
        $_GET = [];
    }

    public function testFlushWithoutNonceAsksForConfirmation(): void
    {
        $this->notices->expects('warning')->once()->with(Mockery::pattern('#href="https://example.com/wp-admin/\?registry-flush=1&amp;_wpnonce=valid"#'));

        $this->flushRequest(true, '');

        $this->assertSame('previous-hash', $this->options[CacheBuster::CACHE_KEY]);
    }

    public function testFlushWithValidNonceResetsCacheBuster(): void
    {
        $this->notices->expects('warning')->never();

        $this->flushRequest(true, 'valid');

        $this->assertSame('', $this->options[CacheBuster::CACHE_KEY]);
        $this->assertSame([], $this->removedRoles);
    }

    public function testRolesAreSavedOnceAfterFlush(): void
    {
        $this->flushRequest(true, 'valid');

        $registry = $this->request(new UserType('manager', 'Manager'));

        $this->assertSame($registry->hash(), $this->options[CacheBuster::CACHE_KEY]);
        $this->assertFalse($registry->hasDiff());
    }

    public function testFlushIsIgnoredForNonAdministrators(): void
    {
        $this->notices->expects('warning')->never();

        $this->flushRequest(false, 'valid');

        $this->assertSame('previous-hash', $this->options[CacheBuster::CACHE_KEY]);
    }

    public function testNewPostTypeRequiresRolesUpdate(): void
    {
        $registry = $this->request(new UserType('administrator', 'Administrator', ['allow_all']));
        $this->assertFalse($registry->hasDiff());

        // allow_all roles must be granted the caps of a new post type
        $this->postTypes['product'] = 'product';
        $this->assertTrue($registry->hasDiff());
    }

    /**
     * Simulate an admin request going through Registry::sync(), with $registerLate registered
     * between the user types registration and the sync (like ShadowTaxonomyHandler on init priority 1).
     */
    private function syncRequest(?callable $registerLate = null, UserType ...$types): Registry
    {
        Functions\when('post_type_exists')->justReturn(false);
        Functions\when('taxonomy_exists')->justReturn(false);
        Functions\when('get_role')->justReturn(null);
        Functions\when('add_role')->alias(function () {
            $this->rolesSaved++;
        });

        $this->resetRegistry();
        $registry = Registry::instance();
        foreach ($types as $type) {
            $registry->attach($type);
        }
        if ($registerLate) {
            $registerLate();
        }
        $registry->sync();
        return $registry;
    }

    public function testSyncSavesRolesOnlyOnceWhenTaxonomyIsRegisteredLate(): void
    {
        $late = function () {
            $this->taxonomies = ['category' => 'category', 'camp_registration' => 'camp_registration'];
        };

        $this->taxonomies = ['category' => 'category'];
        $this->syncRequest($late, new UserType('manager', 'Manager', ['custom' => ['read']]));

        // Next request: the taxonomy is registered again after the user types
        $this->taxonomies = ['category' => 'category'];
        $registry = $this->syncRequest($late, new UserType('manager', 'Manager', ['custom' => ['read']]));

        $this->assertSame(1, $this->rolesSaved);
        $this->assertFalse($registry->hasDiff());
    }

    public function testSyncSavesRolesWhenDefinitionsChange(): void
    {

        $this->syncRequest(null, new UserType('manager', 'Manager', ['custom' => ['read']]));
        $this->syncRequest(null, new UserType('manager', 'Manager', ['custom' => ['read']]));
        $this->syncRequest(null, new UserType('manager', 'Manager', ['custom' => ['read', 'edit_posts']]));

        $this->assertSame(2, $this->rolesSaved);
    }

    public function testPersistSavesRolesWithoutDiff(): void
    {
        $registry = $this->syncRequest(null, new UserType('manager', 'Manager', ['custom' => ['read']]));
        $this->assertFalse($registry->hasDiff());

        $registry->persist();

        $this->assertSame(2, $this->rolesSaved);
        $this->assertFalse($registry->hasDiff());
    }

    public function testSingleAssociativeCapDoesNotTriggerWarning(): void
    {
        \Brain\Monkey\Functions\when('post_type_exists')->justReturn(false);
        \Brain\Monkey\Functions\when('taxonomy_exists')->justReturn(false);

        $type = new UserType('manager', 'Manager', ['custom' => ['read']]);
        (new \ReflectionMethod(UserType::class, 'map'))->invoke($type);

        $this->assertSame(['read' => true], $type->getCaps(true));
    }
}
