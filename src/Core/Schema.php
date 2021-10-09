<?php

namespace Coretik\Core;

use Psr\Container\ContainerInterface;
use Coretik\Core\Builders\Interfaces\BuilderInterface;
use Coretik\Core\Builders\Interfaces\ModelableInterface;
use Coretik\Core\Builders\Interfaces\RegistrableInterface;
use Coretik\Core\Models\Anonymous;
use Coretik\Core\Models\Wp\PostModel;
use Coretik\Core\Models\Wp\TermModel;
use Coretik\Core\Models\Wp\UserModel;

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
    }

    public function register(BuilderInterface $builder)
    {
        if ($builder instanceof RegistrableInterface) {
            \add_action('init', function () use ($builder) {
                $builder->register();
            }, $builder->priority());
        }

        if ($builder instanceof ModelableInterface) {
            if (!$builder->hasFactory()) {
                $builder->factory(function ($initializer) use ($builder) {
                    // @todo apply filters
                    switch ($builder->getType()) {
                        case 'post':
                            return new PostModel($initializer);
                        case 'user':
                            return new UserModel($initializer);
                        case 'taxonomy':
                            return new TermModel($initializer);
                    }
                });
            }
            if (!$builder->hasQuerier()) {
                // $builder->factory(function () {
                //     return new Coretik\Core\Models\Anonymous($builder->getName());
                // });
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
        // if ($builder instanceof Builders\Taxonomy) {
        //     unregister_taxonomy_for_object_type($builder->getName(), 'post');
        //     add_filter('acf/get_taxonomies', function ($taxonomies, $args) use ($builder) {
        //         return array_diff($taxonomies, [$builder->getName()]);
        //     }, 10, 2);
        // }

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

    public function has($offset)
    {
        return isset($this->objects[$offset]);
    }

    public function get($offset, $type = null)
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

    public function __invoke($key = null)
    {
        if (!empty($key)) {
            return $this->get($key);
        }
        return $this;
    }
}
