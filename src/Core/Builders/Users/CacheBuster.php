<?php

namespace Coretik\Core\Builders\Users;

class CacheBuster
{
    const CACHE_KEY = 'app_users_cache_buster';

    public static function get(): string
    {
        return app()->option(static::CACHE_KEY, '');
    }

    public static function set(string $hash)
    {
        app()->option->set(static::CACHE_KEY, $hash, true);
    }
}
