<?php

namespace Coretik\Services\SchemaViewer;

use Coretik\Services\Admin\Menu;

class SchemaViewer
{
    const VIEWS = __DIR__ . '/views/';
    const STYLES = __DIR__ . '/dist/style.css';

    /**
     * Coretik > Schéma (admin_menu)
     */
    public static function init()
    {
        Menu::register();
        \add_submenu_page(
            Menu::SLUG,
            'Schéma',
            'Schéma',
            'manage_options',
            'app-schema-viewer',
            [static::class, 'optionPage']
        );
        add_action('admin_enqueue_scripts', [static::class, 'styles']);
        add_action('admin_enqueue_scripts', [static::class, 'scripts']);
    }

    public static function optionPage()
    {
        include static::VIEWS . 'wrapper.php';
    }

    public static function styles()
    {
        add_action('admin_head', function () {
            ?>
            <style>
                <?php include static::STYLES ?>
            </style>
            <?php
        }, 99);
    }

    public static function scripts()
    {
        add_action('admin_footer', function () {
            ?>
            <script>
                ;(function($) {
                    $('[data-toggle-target]').on('click', function(e) {
                        e.preventDefault();
                        $toggleComponent = $('#' + $(this).data('toggle-target'));
                        var classes = $(this).data('toggle-classes');
                        classes = classes.length ? classes : 'active';
                        $toggleComponent.toggleClass(classes);
                    });
                })(jQuery);
            </script>
            <?php
        }, 99);
    }
}
