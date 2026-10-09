<?php

namespace Coretik;

use Coretik\Core\Container;

/**
 * Container services are reachable as methods (invokable services are called with the arguments).
 *
 * @method \Coretik\Core\Schema|\Coretik\Core\Builders\Interfaces\BuilderInterface|null schema(?string $name = null, ?string $type = null)
 * @method \Coretik\Services\Modals\Container modals()
 * @method \Coretik\Services\Notices\Factory notices()
 * @method mixed forms()
 * @method mixed option(string $key, mixed $default = false)
 * @method mixed menu()
 * @property \Coretik\Core\Models\Wp\Option $option
 *
 * @phpstan-consistent-constructor
 */
class App
{
    private $container;
    protected static $instance = null;

    protected function __construct($container = [])
    {
        if (is_array($container)) {
            $container = new Container($container);
        }

        $this->container = $container;
        $this->init();
    }

    public static function instance()
    {
        return static::$instance;
    }

    public static function run($containers)
    {
        if (empty(static::$instance)) {
            static::$instance = new static($containers);
            \do_action('coretik/app/launched');
        }
    }

    protected function init()
    {
        if ($this->container->has('schemaViewer') && \apply_filters('coretik/app/init/schemaViewer', \defined('WP_DEBUG') && \WP_DEBUG)) {
            \add_action('admin_menu', [$this->get('schemaViewer'), 'init']);
        }

        if ($this->container->has('templating.wrapper') && \apply_filters('coretik/app/init/templating.wrapper', true)) {
            \add_filter('template_include', [$this->get('templating.wrapper'), 'wrap'], 109);
        }

        if ($this->container->has('menu') && \apply_filters('coretik/app/init/menu', true)) {
            $this->menu();
        }

        if ($this->container->has('notices') && \apply_filters('coretik/app/init/notices', true)) {
            \add_action('coretik/app/launched', [$this->get('notices.container'), 'listen']);
        }

        if ($this->container->has('forms') && \apply_filters('coretik/app/init/forms', true)) {
            \add_action('init', function () {
                $singletons = $this->get('forms.singletons');
                if (!empty($singletons)) {
                    foreach ($singletons as $classname) {
                        $this->forms()->attach(new $classname());
                    }
                }

                $factories = $this->get('forms.factories');
                if ($factories) {
                    foreach ($factories as $classname) {
                        $this->forms()->factory(new $classname());
                    }
                }
            });
        }

        \do_action('coretik/app/init', $this);
    }

    public static function __callStatic($method, $args)
    {
        return \call_user_func_array([static::instance(), $method], $args);
    }

    public function __call($method, $args)
    {
        if (!$this->container->has($method)) {
            throw new \BadMethodCallException(\sprintf('Call to undefined method %s::%s(): no "%s" service in the container', static::class, $method, $method));
        }

        $obj = $this->container->get($method);
        if (\is_callable($obj)) {
            return \call_user_func_array($obj, $args);
        }
        return $obj;
    }

    public function has(string $key): bool
    {
        return $this->container->has($key);
    }

    public function get(string $key)
    {
        if ($this->container->has($key)) {
            return $this->container->get($key);
        }
    }

    public function __get($prop)
    {
        return $this->get($prop);
    }
}
