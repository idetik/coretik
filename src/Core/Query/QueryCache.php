<?php

namespace Coretik\Core\Query;

/**
 * WP queries results, by query, for the current request.
 * Flushed on each write that could change a result (posts, terms, users, comments, metas),
 * and limited to MAX_ENTRIES queries (oldest first out) for long running processes (imports, CLI).
 *
 * @phpstan-consistent-constructor
 */
class QueryCache
{
    const MAX_ENTRIES = 100;

    /**
     * WordPress actions after which cached results may be outdated
     */
    const FLUSH_ON = [
        'save_post',
        'deleted_post',
        'set_object_terms',
        'created_term',
        'edited_term',
        'delete_term',
        'user_register',
        'profile_update',
        'deleted_user',
        'set_user_role',
        'wp_insert_comment',
        'edit_comment',
        'deleted_comment',
        'wp_set_comment_status',
        'added_post_meta',
        'updated_post_meta',
        'deleted_post_meta',
        'added_term_meta',
        'updated_term_meta',
        'deleted_term_meta',
        'added_user_meta',
        'updated_user_meta',
        'deleted_user_meta',
        'added_comment_meta',
        'updated_comment_meta',
        'deleted_comment_meta',
    ];

    private $cache = [];
    private static $instance;

    public static function instance()
    {
        if (empty(self::$instance)) {
            self::$instance = new static();
            self::$instance->listen();
        }
        return self::$instance;
    }

    protected function listen(): void
    {
        foreach (static::FLUSH_ON as $action) {
            \add_action($action, [$this, 'flush'], 1, 0);
        }
    }

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->cache);
    }

    public function set(string $key, $object)
    {
        unset($this->cache[$key]);
        $this->cache[$key] = $object;

        $max = (int)\apply_filters('coretik/query/cache/max_entries', static::MAX_ENTRIES);
        while (\count($this->cache) > $max) {
            unset($this->cache[\array_key_first($this->cache)]);
        }
    }

    public function get(string $key)
    {
        return $this->cache[$key];
    }

    public function flush(): void
    {
        $this->cache = [];
    }

    public function count(): int
    {
        return \count($this->cache);
    }
}
