<?php

namespace Coretik\Services\Admin;

/**
 * "Coretik" top level menu gathering the back-office tools of coretik: Schéma, Emails, File d'attente…
 *
 * A tool calls Menu::register() then adds its page with add_submenu_page(Menu::SLUG, …). The menu links to its first
 * page the user can open, and is hidden when there is none.
 *
 * // Label, icon and position of the menu
 * add_filter('coretik/admin/menu', fn (array $options) => ['title' => 'Outils techniques'] + $options);
 */
final class Menu
{
    public const SLUG = 'coretik';

    public const DEFAULTS = [
        'title' => 'Coretik',
        'capability' => 'manage_options',
        'icon' => 'dashicons-admin-generic',
        'position' => 80,
    ];

    /**
     * Adds the menu once (WordPress hooks)
     */
    public static function register(): void
    {
        if (!\has_action('admin_menu', [self::class, 'add'])) {
            \add_action('admin_menu', [self::class, 'add'], 5);
            \add_action('admin_menu', [self::class, 'clean'], 999);
        }

        // Registered while the menus are built (a tool added at admin_menu): add it now
        if (\doing_action('admin_menu') && !self::exists()) {
            self::add();
        }
    }

    /**
     * @return array{title: string, capability: string, icon: string, position: int|float}
     */
    public static function options(): array
    {
        return (array)\apply_filters('coretik/admin/menu', []) + self::DEFAULTS;
    }

    public static function add(): void
    {
        if (self::exists()) {
            return;
        }

        $options = self::options();
        \add_menu_page($options['title'], $options['title'], $options['capability'], self::SLUG, '', $options['icon'], $options['position']);
    }

    /**
     * WordPress repeats the menu as its first sub-item, a page without content: removed, the menu links to the first tool
     */
    public static function clean(): void
    {
        \remove_submenu_page(self::SLUG, self::SLUG);
    }

    protected static function exists(): bool
    {
        global $menu;

        foreach ((array)$menu as $item) {
            if (self::SLUG === ($item[2] ?? null)) {
                return true;
            }
        }

        return false;
    }
}
