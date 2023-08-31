<?php

namespace Coretik\Core\Builders;

use Coretik\Core\Builders\Interfaces\{
    RegistrableInterface,
    HandlerInterface
};
use Coretik\Core\Collection;
use Coretik\Core\Builders\Traits\Registrable;
use Coretik\Core\Builders\Users\Registry;
use Coretik\Core\Query\User as Query;

final class UserType extends BuilderModelable implements RegistrableInterface
{
    use Registrable {
        registrable as registrableInherited;
    }

    protected $registerPriority = 1;
    protected $name;
    protected $label;
    protected $caps;
    protected $capsMapped;

    public function __construct(string $name, string $label, array $caps = [])
    {
        $this->name = $name;
        $this->label = $label;
        $this->caps = new Collection($caps);
        $this->capsMapped = new Collection();
        parent::__construct();

        $this->handler(new class implements HandlerInterface {
            public function handle($builder): void
            {
                Registry::instance()->attach($builder);
            }
            public function freeze(): void
            {
                // nothing
            }
        });
        $this->querier(function ($mediator) {
            return new Query($mediator);
        });
    }

    public function __sleep()
    {
        return ['name', 'label', 'caps'];
    }

    public function getType(): string
    {
        return 'user';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getCaps(bool $mapped = false): array
    {
        if ($mapped) {
            $this->map();
            return $this->capsMapped->all();
        }
        return $this->caps->all();
    }

    public function registrable(): bool
    {
        if (!\is_admin()) {
            return false;
        }
        if (!Registry::instance()->hasDiff()) {
            return false;
        }
        return $this->registrableInherited();
    }

    public function registerAction(): void
    {
        $this->map();
        $this->save();
        if (!\has_action('init', [Registry::instance(), 'save'])) {
            \add_action('init', [Registry::instance(), 'save'], $this->registerPriority + 1);
        }
    }

    public function wpObject(int $id)
    {
        return \get_user_by('id', $id);
    }

    protected function map()
    {
        // Allow all caps (should be used for admin only)
        if (1 === $this->caps->count() && 'allow_all' === $this->caps[0]) {
            $default_caps = static::defaultWpCaps();
            $this->addCaps($default_caps, true, true);

            // Grant all post types caps
            $post_types = get_post_types([], 'objects');
            foreach ($post_types as $post_type_object) {
                $post_type_caps = \get_object_vars($post_type_object->cap);
                $caps_granted = \array_values($post_type_caps);
                $this->addCaps($caps_granted);
            }

            // Grant all taxonomies caps
            $taxonomies = get_taxonomies([], 'objects');
            foreach ($taxonomies as $taxonomy) {
                $tax_caps = \get_object_vars($taxonomy->cap);
                $this->addCaps(array_values($tax_caps));
            }
            return;
        }


        foreach ($this->caps as $post_type => $caps_granted) {
            // Custom caps
            if ('custom' === $post_type) {
                $this->addCaps($caps_granted);
            } elseif (post_type_exists($post_type)) {
                $post_type_object   = \get_post_type_object($post_type);
                $post_type_caps     = \get_object_vars($post_type_object->cap);

                if (\is_string($caps_granted) && 'allow_all' === $caps_granted) {
                    // Grant all post type caps
                    $caps_granted = array_values($post_type_caps);
                } else {
                    // Grant only specified caps (mapped with capability type)
                    $caps_granted = array_values(array_intersect_key($post_type_caps, array_flip($caps_granted)));
                }
                $this->addCaps($caps_granted);
            } elseif (\taxonomy_exists($post_type)) {
                $taxonomy = \get_taxonomy($post_type);
                $tax_caps = \get_object_vars($taxonomy->cap);

                if (\is_string($caps_granted) && 'allow_all' === $caps_granted) {
                    // Grant all tax caps
                    $caps_granted = \array_values($tax_caps);
                } else {
                    // Grant only specified caps (mapped with capability type)
                    $caps_granted = \array_values(\array_intersect_key($tax_caps, \array_flip($caps_granted)));
                }
                $this->addCaps($caps_granted);
            } else {
                add_action('admin_notices', function () use ($post_type) {
                    printf('<div class="%s"><p>%s</p></div>', 'notice notice-error', 'Undefined post type or taxonomy "' . $post_type . '" for user role "' . $this->label . '"');
                });
            }
        }
    }

    public function save()
    {
        $exists = \get_role($this->name);
        if (!$exists) {
            \add_role($this->name, $this->label, $this->capsMapped->all());
        } else {
            if ('administrator' !== $exists->name) {
                $old_caps = $exists->capabilities;
                foreach ($old_caps as $cap => $grant) {
                    $exists->remove_cap($cap);
                }
            }

            foreach ($this->capsMapped as $new_cap => $grant) {
                $exists->add_cap($new_cap, $grant);
            }
        }
    }

    public function addCap($cap, $granted = true, $override = false)
    {
        if ($override || !$this->capsMapped->has($cap)) {
            $this->capsMapped->set($cap, $granted);
        }
    }

    public function addCaps(array $caps, $granted = true, $override = false)
    {
        foreach ($caps as $cap) {
            $this->addCap($cap, $granted, $override);
        }
    }

    public function delete()
    {
        if ('administrator' === $this->name) {
            return;
        }

        $exists = \get_role($this->name);
        if ($exists) {
            \remove_role($this->name);
        }
    }

    protected static function defaultWpCaps()
    {
        $caps = [
            'access_wp_admin',
            'edit_dashboard',
            'edit_files',
            'export',
            'import',
            'manage_links',
            'manage_options',
            'moderate_comments',
            'read',
            'unfiltered_html',
            'update_core',
            'upload_files',
            'manage_categories',
            'delete_themes',
            'edit_theme_options',
            'edit_themes',
            'install_themes',
            'switch_themes',
            'update_themes',
            'activate_plugins',
            'delete_plugins',
            'edit_plugins',
            'install_plugins',
            'update_plugins',
            'create_roles',
            'create_users',
            'delete_roles',
            'delete_users',
            'edit_roles',
            'edit_users',
            'list_roles',
            'list_users',
            'promote_users',
            'remove_users',
        ];

        $tax_caps = [
            'assign_categories',
            'edit_categories',
            'delete_categories',
            'assign_post_tags',
            'edit_post_tags',
            'delete_post_tags',
            'manage_post_tags',
        ];

        $custom_caps = [
            // '', // Custom cap used in page options
        ];

        return \apply_filters('coretik/core/builders/usertype/default_wp_caps', \array_merge($caps, $tax_caps, $custom_caps), $caps, $tax_caps, $custom_caps);
    }

    public function concern(int $objectId): bool
    {
        $data = get_userdata($objectId);

        if (false === $data) {
            return false;
        }
        return \in_array($this->getName(), $data->roles);
    }
}
