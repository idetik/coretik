<?php

namespace Coretik\Tests\Integration;

use Coretik\Services\Admin\Menu;
use Coretik\Services\Email\Viewer\Catalog;
use Coretik\Services\Email\Viewer\Preview;
use Coretik\Services\Email\Viewer\Viewer;
use Coretik\Services\Queue\Admin;
use Coretik\Services\Queue\DatabaseStore;
use Coretik\Services\Queue\Queue;
use Coretik\Services\SchemaViewer\SchemaViewer;

/**
 * "Coretik" top level menu gathering Schéma, Emails, File d'attente
 */
class AdminMenuTest extends IntegrationTestCase
{
    public function set_up(): void
    {
        parent::set_up();
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        global $menu, $submenu, $_wp_submenu_nopriv, $_registered_pages;
        $menu = $submenu = $_wp_submenu_nopriv = $_registered_pages = [];
        // Schema viewer hooked by App::init() (WP_DEBUG): each test adds its own tools (hooks restored after the test)
        remove_all_actions('admin_menu');
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
    }

    private function catalog(): Catalog
    {
        return new class implements Catalog {
            public function entries(): array
            {
                return [];
            }

            public function context(array $query): array
            {
                return [];
            }

            public function contextFields(array $context): string
            {
                return '';
            }

            public function preview(string $key, array $context): ?Preview
            {
                return null;
            }

            public function sendTest(string $key, array $context, string $to): bool
            {
                return false;
            }
        };
    }

    /**
     * @return array<string, string> Top level slugs => titles
     */
    private function topLevel(): array
    {
        global $menu;
        return array_column((array)$menu, 0, 2);
    }

    /**
     * @return string[] Page slugs of the Coretik menu
     */
    private function coretikPages(): array
    {
        global $submenu;
        return array_column($submenu[Menu::SLUG] ?? [], 2);
    }

    public function testToolsAreGatheredInTheCoretikMenu(): void
    {
        (new Viewer($this->catalog()))->hooks();
        (new Admin(new Queue(new DatabaseStore())))->hooks();
        add_action('admin_menu', [SchemaViewer::class, 'init']);

        do_action('admin_menu');

        $this->assertSame('Coretik', $this->topLevel()[Menu::SLUG]);
        $this->assertSame(['coretik-emails', 'coretik-queue', 'app-schema-viewer'], $this->coretikPages());
        $this->assertArrayNotHasKey('coretik-emails', $this->topLevel());
        $this->assertArrayNotHasKey('app-schema-viewer', $this->topLevel());
    }

    public function testMenuIsAddedOnce(): void
    {
        Menu::register();
        Menu::register();
        (new Admin(new Queue(new DatabaseStore())))->hooks();

        do_action('admin_menu');

        $this->assertCount(1, array_filter(array_keys($this->topLevel()), fn ($slug) => Menu::SLUG === $slug));
    }

    public function testMenuOptionsAreFiltered(): void
    {
        add_filter('coretik/admin/menu', fn (array $options) => ['title' => 'Outils techniques'] + $options);
        (new Admin(new Queue(new DatabaseStore())))->hooks();

        do_action('admin_menu');

        $this->assertSame('Outils techniques', $this->topLevel()[Menu::SLUG]);
    }

    public function testToolCanKeepItsOwnPlace(): void
    {
        (new Viewer($this->catalog(), ['parent' => null, 'slug' => 'my-emails']))->hooks();
        (new Admin(new Queue(new DatabaseStore()), ['parent' => 'tools.php']))->hooks();

        do_action('admin_menu');

        global $submenu;
        $this->assertArrayHasKey('my-emails', $this->topLevel());
        $this->assertContains('coretik-queue', array_column($submenu['tools.php'] ?? [], 2));
        $this->assertArrayNotHasKey(Menu::SLUG, $this->topLevel(), 'No Coretik menu without tool in it');
    }
}
