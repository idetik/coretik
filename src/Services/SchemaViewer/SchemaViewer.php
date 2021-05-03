<?php

namespace Coretik\Services\SchemaViewer;

class SchemaViewer
{
    const VIEWS = __DIR__ . '/views/';
    const STYLES = __DIR__ . '/dist/style.css';

    public static function init()
    {
        \add_menu_page(
            'Schéma',
            'Schéma',
            'manage_options',
            'app-schema-viewer',
            [static::class, 'optionPage'],
            'dashicons-admin-generic',
            100
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


    // @todo: TEMP
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
                        // $(this).toggleClass(classes);
                    });
                })(jQuery);
            </script>
            <?php
        }, 99);
    }
}
