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

    protected function setUp(): void
    {
        parent::setUp();

        $this->options = [];
        $this->removedRoles = [];

        Functions\when('get_option')->alias(fn ($key, $default = false) => $this->options[$key] ?? $default);
        Functions\when('add_option')->alias(function ($key, $value) {
            $this->options[$key] = $value;
        });
        Functions\when('update_option')->alias(function ($key, $value) {
            $this->options[$key] = $value;
        });
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

    public function testFlushIsIgnoredForNonAdministrators(): void
    {
        $this->notices->expects('warning')->never();

        $this->flushRequest(false, 'valid');

        $this->assertSame('previous-hash', $this->options[CacheBuster::CACHE_KEY]);
    }
}
