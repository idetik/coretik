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

$container['my-service'] = function ($container) {
    return new MyService();
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
        [...]
    ],
    ['singular' => 'Title', 'plural' => 'Titles']
);

// You can add a custom model factory or use the default factory
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

### Querying models
#### Simple query

One way to query all wp_post filtered by default query args, and browse result models :

See `src/Core/Query/Post::getQueryArgsDefault()`

```
$models = app()->schema('post')->query()->models();

foreach ($models as $model) {
    echo $model->title();
}
```
#### Others query
See `src/Core/Query/Adapters` folder. 


### Set a custom query class for object
#### Setup

```
use Coretik\Core\Query\Post as PostQuery;

class MyPostQuery enxtends PostQuery
{
    public function myCustomFilter()
    {
        $this->where([...]);
        return $this;
    }
}

$postSchema = app()->schema('post');
$postSchema->querier(fn ($builder) => new MyPostQuery($builder));
```

#### Usage

```
$models = app()->schema('post')->query()->myCustomFilter()->models();

foreach ($models as $model) {
    echo $model->title();
}
```

### Set a custom model class for object
#### Setup

```
use Coretik\Core\Models\Wp\PostModel;

class MyPostModel enxtends PostModel
{
    public function foo()
    {
        [...]
    }
}

$postSchema = app()->schema('post');
$postSchema->factory(fn ($initializer) => new MyPostModel($initializer));
```

#### Usage

```
$models = app()->schema('post')->query()->models();

foreach ($models as $model) {
    echo $model->foo();
}
```
