<?php

namespace Coretik\Core\Builders\Taxonomy;

use Coretik\Core\Collection;
use Coretik\Core\Utils;

/**
 * see @https://developer.wordpress.org/reference/functions/register_post_type/
 * see @https://github.com/johnbillion/extended-cpts/
 */

class Args extends Collection
{
    protected $default = [
        'description'        => '',
        'public'             => true,
        'publicly_queryable' => true,
        'hierarchical'       => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_nav_menus'  => true,
        // 'show_in_rest'        => '',
        // 'rest_base'           => '',
        // 'rest_controller_class' => 'WP_REST_Terms_Controller',
        'show_tagcloud'      => false,
        'show_in_quick_edit' => false,
        'show_admin_column'  => true,
        'meta_box_cb'        => false,
        'labels'             => null,
        //'capabilities'       => [],
        'rewrite'            => null,
        'default_term'       => null,
        // Extended
        'exclusive'         => false, // true means: just one can be selected
        'allow_hierarchy'   => false,
        'meta_box'          => false, // can be null, 'simple', 'radio', 'dropdown' -> 'radio' and 'dropdown' just allow exclusive choices (will overwrite the set choise), simple has exclusive and multi option
        'dashboard_glance'  => false, // Show this taxonomy in the 'At a Glance' dashboard widget:
        'checked_ontop'     => null,
        'admin_cols'        => false, // Add a custom column to the admin screen:
        'required'          => false,
    ];

    public function __construct(array $items = [])
    {
        $items = \array_merge($this->default, $items);
        parent::__construct($items);
    }
}
