<?php

namespace Coretik\Core\Builders\PostType;

use Coretik\Core\Collection;
use Coretik\Core\Utils;

/**
 * see @https://developer.wordpress.org/reference/functions/register_post_type/
 * see @https://github.com/johnbillion/extended-cpts/
 */

class Args extends Collection
{
    protected $default = [
        'description'         => '',
        'hierarchical'        => false,
        'public'              => true,
        'exclude_from_search' => false,
        'publicly_queryable'  => true,
        'show_ui'             => true,
        'menu_position'       => 6,
        'show_in_menu'        => true,
        'show_in_nav_menus'   => true,
        'show_in_admin_bar'   => true,
        // 'show_in_rest'        => '',
        // 'rest_base'           => '',
        // 'rest_controller_class' => 'WP_REST_Posts_Controller',
        // 'menu_icon'           => 'dashicons-',
        'capability_type'     => 'post',
        //'capabilities'        => [],
        'map_meta_cap'        => true,
        'supports'            => ['title', 'editor', 'thumbnail'],
        'register_meta_box_cb' => null,
        'taxonomies'          => [],
        'has_archive'         => true,
        'rewrite'             => [
            // 'slug' => '',
            'pages' => true,
            'feeds' => true,
            'with_front' => false,
            'ep_mask' => EP_PERMALINK
        ],
        'query_var'           => false,
        'labels'              => null,
        'can_export'          => true,
        'delete_with_user'    => null,

        // Extended
        'show_in_feed'         => false,
        'quick_edit'           => true,
        'dashboard_glance'     => true,
        'dashboard_activity'   => false,
        'enter_title_here'     => null,
        'featured_image'       => null,
        'site_filters'         => null,
        'site_sortables'       => null,
        'archive'              => null,
        'admin_cols'           => [],
        'admin_filters'        => [],
        'block_editor'         => null,
    ];

    public function __construct(array $items = [])
    {
        $items = \array_merge($this->default, $items);
        parent::__construct($items);
    }
}
