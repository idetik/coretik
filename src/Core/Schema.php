<?php

namespace Coretik\Core;

use Psr\Container\ContainerInterface;
use Coretik\Core\Builders\Interfaces\BuilderInterface;
use Coretik\Core\Builders\Interfaces\ModelableInterface;
use Coretik\Core\Builders\Interfaces\RegistrableInterface;
use Coretik\Core\Exception\ContainerValueNotFoundException;
use Coretik\Core\Models\Interfaces\ModelInterface;
use Coretik\Core\Models\Wp\PostModel;
use Coretik\Core\Models\Wp\TermModel;
use Coretik\Core\Models\Wp\UserModel;
use Coretik\Core\Models\Wp\CommentModel;
use Coretik\Core\Query\Post as PostQuery;
use Coretik\Core\Query\User as UserQuery;
use Coretik\Core\Query\Term as TermQuery;
use Coretik\Core\Query\Comment as CommentQuery;

class Schema implements ContainerInterface
{
    protected $objects = [];
    protected $types = [];

    public function __construct()
    {
        $this->register(new Builders\PostTypeBuiltIn('page'));
        $this->register(new Builders\PostTypeBuiltIn('post'));
        $this->register(new Builders\TaxonomyBuiltIn('category'));
        $this->register(new Builders\TaxonomyBuiltIn('post_tag'));
        $this->register(new Builders\PostTypeBuiltIn('attachment'));
        $this->register(new Builders\User());
        $this->register(new Builders\Comment());
    }

    public function register(BuilderInterface $builder)
    {
        if ($builder instanceof RegistrableInterface) {
            \add_action('init', function () use ($builder) {
                $builder->register();
            }, $builder->priority());
        }

        if ($builder instanceof ModelableInterface) {
            // Set model factory if empty
            if (!$builder->hasFactory()) {
                $builder->factory(function ($initializer) use ($builder) {

                    $factory = \apply_filters('coretik/schema/factory', null, $initializer, $builder);
                    if (!empty($factory)) {
                        return $factory;
                    }

                    switch ($builder->getType()) {
                        case 'post':
                            return \apply_filters('coretik/schema/factory/post', new PostModel($initializer), $initializer, $builder);
                        case 'user':
                            return \apply_filters('coretik/schema/factory/user', new UserModel($initializer), $initializer, $builder);
                        case 'taxonomy':
                            return \apply_filters('coretik/schema/factory/taxonomy', new TermModel($initializer), $initializer, $builder);
                        case 'comment':
                            return \apply_filters('coretik/schema/factory/comment', new CommentModel($initializer), $initializer, $builder);
                        default:
                            return \apply_filters('coretik/schema/factory/' . $builder->getType(), null, $initializer, $builder);
                    }
                });
            }

            // Set model querier if empty
            if (!$builder->hasQuerier()) {
                $builder->querier(function ($builder) {

                    $querier = \apply_filters('coretik/schema/querier', null, $builder);
                    if (!empty($querier)) {
                        return $querier;
                    }

                    switch ($builder->getType()) {
                        case 'post':
                            return \apply_filters('coretik/schema/querier/post', new PostQuery($builder), $builder);
                        case 'user':
                            return \apply_filters('coretik/schema/querier/user', new UserQuery($builder), $builder);
                        case 'taxonomy':
                            return \apply_filters('coretik/schema/querier/taxonomy', new TermQuery($builder), $builder);
                        case 'comment':
                            return \apply_filters('coretik/schema/querier/comment', new Comment($builder), $builder);
                        default:
                            return \apply_filters('coretik/schema/querier/' . $builder->getType(), null, $builder);
                    }
                });
            }
        }

        $builder->runHandlers();

        if (!in_array($builder->getType(), $this->types)) {
            $this->types[] = $builder->getType();
            $this->objects[$builder->getType()] = new BuilderCollection();
        }

        $this->objects[$builder->getType()]->set($builder->getName(), $builder);
    }

    public function unregister(BuilderInterface $builder)
    {
        if ($builder instanceof Builders\PostTypeBuiltIn) {
            switch ($builder->getName()) {
                case 'post':
                    \add_action('admin_menu', function () {
                        \remove_menu_page('edit.php');
                    });
                    \add_action('admin_bar_menu', function ($wp_admin_bar) {
                        $wp_admin_bar->remove_node('new-post');
                    }, 999);
                    \add_action('wp_dashboard_setup', function () {
                        \remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
                    }, 999);
                    $this->objects['taxonomy']->remove('category');
                    $this->objects['taxonomy']->remove('post_tag');
                    break;
            }
        }

        if ($builder instanceof Builders\PostType) {
            \unregister_post_type($builder->getName());
        }

        $this->objects[$builder->getType()]->remove($builder->getName());
    }

    public function has(string $offset): bool
    {
        return isset($this->objects[$offset]);
    }

    public function get(string $offset, $type = null)
    {
        if (!empty($type)) {
            return $this->objects[$type]->get($offset) ?? null;
        }

        foreach ($this->objects as $type => $data) {
            if ($data->has($offset)) {
                return $data->get($offset);
            }
        }
        return null;
    }

    /**
     * @param string $type in 'post', 'user', 'status', ...etc
     */
    public function type(string $type)
    {
        return $this->objects[$type];
    }

    public function toArray()
    {
        return array_map(function ($collection) {
            return $collection->keys();
        }, $this->objects);
    }

    public function resolve(string|BuilderInterface|ModelInterface $builder): BuilderInterface
    {
        if ($builder instanceof ModelInterface) {
            $builder = match (true) {
                $builder instanceof PostModel => $this->get($builder->name(), 'post'),
                $builder instanceof TermModel => $this->get($builder->name(), 'taxonomy'),
                $builder instanceof CommentModel => $this->get($builder->name(), 'comment'),
                $builder instanceof UserModel => $this->get($builder->name(), 'user'),
                default => $this->get($builder->name())
            };
        }

        if ($builder instanceof BuilderInterface) {
            return $builder;
        }

        if (!empty(($object = app()->schema()->get($builder)))) {
            return $object;
        }

        throw new ContainerValueNotFoundException;
    }

    public function __invoke($key = null)
    {
        if (!empty($key)) {
            return $this->get($key);
        }
        return $this;
    }
}
