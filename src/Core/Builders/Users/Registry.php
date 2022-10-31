<?php

namespace Coretik\Core\Builders\Users;

use Coretik\App;

class Registry extends \SplObjectStorage
{
    const OPTION_KEY = 'app_users_registry';
    const QUERY_VAR_FLUSH = 'registry-flush';

    private static $instance;
    private $prev;

    public static function hooks()
    {
        \add_action('init', [static::class, 'triggerFlush'], 0);
    }

    public static function triggerFlush()
    {
        if (!\is_admin()) {
            return;
        }

        if (!\current_user_can('administrator')) {
            return;
        }

        if (!isset($_GET[static::QUERY_VAR_FLUSH])) {
            return;
        }

        $instance = static::instance();
        $instance->removeAll($instance);
        $instance->save()->cleanup();
    }

    public function getHash(object $o): string
    {
        return md5(serialize($o));
    }

    public function save()
    {
        $this->prev = App::option(static::OPTION_KEY);
        App::instance()->option->set(static::OPTION_KEY, $this, false);
        CacheBuster::set($this->hash());
        $this->cleanup();
        App::notices()->success('Roles & capabilities updated.');
        return $this;
    }

    public static function instance()
    {
        if (empty(static::$instance)) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    public function hash()
    {
        $cache = [];
        foreach ($this as $o) {
            $cache[] = serialize($o);
        }
        return md5(serialize($cache));
    }

    public function hasDiff()
    {
        return CacheBuster::get() !== $this->hash();
    }

    public function cleanup()
    {
        if (empty($this->prev)) {
            return;
        }

        foreach ($this->prev as $previous) {
            if (!$this->contains($previous)) {
                $previous->delete();
            }
        }
    }

    public function __serialize(): array
    {
        $this->prev = null;
        return parent::__serialize();
    }

    public static function __callStatic($method, $args)
    {
        return \call_user_func([static::instance(), $method], $args);
    }
}

Registry::hooks();
