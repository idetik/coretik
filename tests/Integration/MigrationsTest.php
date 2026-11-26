<?php

namespace Coretik\Tests\Integration;

use Coretik\Services\Migrations\Admin;
use Coretik\Services\Migrations\Migrations;
use Coretik\Services\Migrations\OptionStatusStore;
use Coretik\Services\Migrations\Result;
use Coretik\Tests\Unit\Migrations\CountingMigration;
use Coretik\Tests\Unit\Migrations\CountingSeeder;
use Coretik\Tests\Unit\Migrations\FailingMigration;

/**
 * Migrations with their statuses in the option coretik_migrations
 */
class MigrationsTest extends IntegrationTestCase
{
    private Migrations $migrations;

    public function set_up(): void
    {
        parent::set_up();
        CountingMigration::$runs = [];
        $this->migrations = (new Migrations())->add(CountingMigration::class, CountingSeeder::class, FailingMigration::class);
    }

    public function testStatusesAreStoredInAnOptionNotAutoloaded(): void
    {
        $this->migrations->run('counting');
        $this->migrations->run('failing');

        $statuses = get_option(OptionStatusStore::OPTION);
        $this->assertSame(Result::DONE, $statuses['counting']['status']);
        $this->assertEqualsWithDelta(time(), $statuses['counting']['ran_at'], 5);
        $this->assertSame(['status' => Result::FAILED, 'message' => 'Nothing to convert'], array_diff_key($statuses['failing'], ['ran_at' => 0]));

        global $wpdb;
        $autoload = $wpdb->get_var($wpdb->prepare("SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", OptionStatusStore::OPTION));
        $this->assertContains($autoload, ['no', 'off']);
    }

    public function testMigrationsRunOnceAcrossRequests(): void
    {
        $this->migrations->migrate();

        // Next request: new registry, same option
        $next = (new Migrations())->add(CountingMigration::class, CountingSeeder::class, FailingMigration::class);

        $this->assertSame(['failing'], $next->pending());
        $this->assertSame(Result::SKIPPED, $next->run('counting')->status);
        $this->assertSame(['counting', 'failing'], CountingMigration::$runs, 'Seeder not run by migrate()');
    }

    public function testOptionStoreForget(): void
    {
        $store = new OptionStatusStore();
        $store->set('counting', Result::DONE);
        $store->forget('counting');

        $this->assertNull($store->get('counting'));
        $this->assertSame([], $store->all());
    }

    public function testBackOfficePageListsTheMigrations(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        $this->migrations->run('counting');
        $admin = new Admin($this->migrations);
        set_transient($admin->action('results') . '_' . get_current_user_id(), [(new Result('counting', Result::DONE, '', [['message' => 'Run <b>1</b><script>x</script>', 'type' => 'success']]))->toArray()]);

        ob_start();
        $admin->page();
        $html = (string)ob_get_clean();

        $this->assertStringContainsString('<code>counting</code>', $html);
        $this->assertStringContainsString('Counts its runs', $html);
        $this->assertStringContainsString('Lancer les migrations en attente (1)', $html, 'failing is pending');
        $this->assertStringContainsString('Relancer', $html);
        $this->assertStringContainsString('<li>Run <b>1</b>x</li>', $html, 'Logs: HTML kept, scripts removed');
        $this->assertStringContainsString('_wpnonce=', $html);
        $this->assertSame([], $admin->pullResults(), 'Results shown once');
    }

    public function testBackOfficePageNeedsTheCapability(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'editor']));
        add_filter('wp_die_handler', fn () => function ($message) {
            throw new \RuntimeException((string)$message);
        });

        $this->expectException(\RuntimeException::class);
        (new Admin($this->migrations))->page();
    }
}
