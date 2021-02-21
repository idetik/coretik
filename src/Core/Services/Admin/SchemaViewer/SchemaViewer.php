<?php

namespace Coretik\Core\Services\Admin\SchemaViewer;

use function Globalis\WP\Cubi\include_template_part;

class SchemaViewer
{
    const VIEWS = '/src/Services/Admin/SchemaViewer/views/';

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
        include_template_part(static::VIEWS . 'wrapper');
    }
}
