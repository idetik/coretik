<?php

namespace Coretik\Core\Services\Admin\SchemaViewer;

class SchemaViewer
{
    const VIEWS = __DIR__ . '/views/';

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
        add_action('', [static::class, 'styles']);
    }

    public static function optionPage()
    {
        include static::VIEWS . 'wrapper.php';
    }
}
