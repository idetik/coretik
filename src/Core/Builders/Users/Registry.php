<?php

namespace Coretik\Core\Builders\Users;

class Registry extends \SplObjectStorage
{
    const OPTION_KEY = 'app_users_registry';
    const QUERY_VAR_FLUSH = 'registry-flush';

    private static $instance;

    /**
     * @deprecated Kept to unserialize registries stored before 1.13.7 (serialized objects)
     */
    private $prev;

    public static function hooks()
    {
        \add_action('init', [static::class, 'triggerFlush'], 0);
    }

    /**
     * ?registry-flush asks for a confirmation link with a nonce, then forces roles to be saved again on this request.
     * Roles are updated in place: users never lose their capabilities in between.
     */
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

        if (!\wp_verify_nonce($_GET['_wpnonce'] ?? '', static::QUERY_VAR_FLUSH)) {
            app()->notices()->warning(\sprintf(
                'Regenerate roles & capabilities? <a href="%s">Confirm</a>',
                \esc_url(static::flushUrl())
            ));
            return;
        }

        // User types registered on this request will see a diff and save their role again
        CacheBuster::set('');
    }

    public static function flushUrl(): string
    {
        return \wp_nonce_url(\add_query_arg(static::QUERY_VAR_FLUSH, 1, \admin_url()), static::QUERY_VAR_FLUSH);
    }

    public function getHash(object $o): string
    {
        return md5(serialize($o));
    }

    public function save()
    {
        $previous = $this->storedRoleNames();
        \update_option(static::OPTION_KEY, $this->toArray(), false);
        CacheBuster::set($this->hash());
        $this->cleanup($previous);
        app()->notices()->success('Roles & capabilities updated.');
        return $this;
    }

    public static function instance()
    {
        if (empty(static::$instance)) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * Role definitions, by role name. This is what is stored in database.
     */
    public function toArray(): array
    {
        $roles = [];
        foreach ($this as $userType) {
            $roles[$userType->getName()] = [
                'label' => $userType->getLabel(),
                'caps' => $userType->getCaps(),
            ];
        }
        return $roles;
    }

    public function hash()
    {
        return md5(serialize($this->toArray()));
    }

    public function hasDiff()
    {
        return CacheBuster::get() !== $this->hash();
    }

    /**
     * Remove the roles which are no longer registered.
     *
     * @param string[] $previous Role names registered before
     */
    public function cleanup(array $previous = [])
    {
        $current = \array_keys($this->toArray());

        foreach (\array_diff($previous, $current) as $name) {
            if ('administrator' === $name) {
                continue;
            }
            if (\get_role($name)) {
                \remove_role($name);
            }
        }
    }

    /**
     * Role names stored in database, whatever the storage format.
     */
    protected function storedRoleNames(): array
    {
        $stored = \get_option(static::OPTION_KEY, []);

        if (\is_array($stored)) {
            return \array_keys($stored);
        }

        // Before 1.13.7: the registry itself was stored (serialized UserType objects)
        if ($stored instanceof \SplObjectStorage) {
            $names = [];
            foreach ($stored as $userType) {
                if (\is_object($userType) && \method_exists($userType, 'getName')) {
                    $names[] = $userType->getName();
                }
            }
            return $names;
        }

        // Unreadable value (e.g. __PHP_Incomplete_Class): nothing to clean up
        return [];
    }

    public static function __callStatic($method, $args)
    {
        return \call_user_func([static::instance(), $method], ...$args);
    }
}

Registry::hooks();
