# Coretik : Wordpress framework

To manage models, queries, services and more...

## Installation

`composer require idetik/coretik`


## Get started

### Dependency Injection Container

Declare dependencies in your theme :

```
use Coretik\App;
use Coretik\Core\Container;
use Coretik\Services\Menu\Menu;

$container = new Container();

// Declare menu location
$container['menu'] = function ($container) {
    return new Menu([
        'header' => 'Menu principal',
        'footer' => 'Pied de page',
    ]);
};

[...]
App::run($container);
```

### Schema : declare post type and others WP objects

```
use Coretik\Core\Builders\Taxonomy;
use Coretik\Core\Builders\PostType;
use Coretik\Core\Collection;

use THEME\NAMESPACE\PostTypeModel;

// Declare post type
$myPostType = new PostType(
    'my_post_type',
    [
        'menu_icon' => 'dashicons-food',
        'is_femininus' => false,
        'has_archive' => true,
        'use_archive_page' => true,
        'admin_filters' => [
            'type' => [
                'taxonomy' => 'my_taxonomy'
            ]
        ]
    ],
    ['singular' => 'Title', 'plural' => 'Titles']
);
$myPostType->factory(function ($initializer) {
    return new PostTypeModel($initializer);
});

// Declare taxonomy
$myPostType_tax = new Taxonomy(
    'my_taxonomy',
    $myPostType,
    [
        'meta_box' => 'simple',
        'hierarchical' => true,
    ],
    [
        'singular' => 'Title',
        'plural' => 'Titles
    ]
);

app()->schema()->register($myPostType);
app())>schema()->register($myPostType_tax);

```
