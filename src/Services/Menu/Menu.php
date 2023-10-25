<?php

namespace Coretik\Services\Menu;

use Coretik\Services\Menu\Walkers\Aria;

class Menu
{
    protected array $menus;
    protected array $cache = [];

    public function __construct(array $menus = [])
    {
        $this->menus = $menus;
        \add_action('after_setup_theme', [$this, 'register']);
    }

    public static function make(array $menus = []): self
    {
        $self = new static();
        foreach ($menus as $location => $title) {
            $self->addEntry($location, $title);
        }
        return $self;
    }

    public function register(): void
    {
        \register_nav_menus($this->menus);
    }

    public function addEntry(string $theme_location, string $title): self
    {
        $this->menus[$theme_location] = $title;
        return $this;
    }

    public function html(string $theme_location, array $custom_args = [], string $builtInWalker = '', bool $cache = false): string
    {
        if (isset($this->cache[$theme_location])) {
            return $this->cache[$theme_location];
        }

        $args = [
            'echo'           => false,
            'theme_location' => $theme_location,
            'container'      => false,
        ];

        if (empty($custom_args['walker']) && !empty($builtInWalker)) {
            switch ($builtInWalker) {
                case 'aria':
                    $custom_args['walker'] = new Aria();
                    if (empty($custom_args['items_wrap'])) {
                        $custom_args['items_wrap'] = '<ul id="%1$s" class="%2$s" role="menubar">%3$s</ul>';
                    }
                    break;
            }
        }

        if (\has_nav_menu($theme_location)) {
            $menu = \wp_nav_menu(array_merge($args, $custom_args));
        } else {
            $menu = '';
        }

        if ($cache) {
            $this->cache[$theme_location] = $menu;
        }

        return $menu;
    }

    public function object(string $theme_location): ?\WP_Term
    {
        $locations = \get_nav_menu_locations();
        if (!in_array($theme_location, array_keys($locations))) {
            return null;
        }
        $menu_id = $locations[$theme_location];
        return \wp_get_nav_menu_object($menu_id) ?: null;
    }

    public function title(string $theme_location, string $default = ''): string
    {
        if (!\has_nav_menu($theme_location)) {
            return '';
        }
        return $this->object($theme_location)?->name ?: $default;
    }
}
