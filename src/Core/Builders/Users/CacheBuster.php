<?php

namespace Coretik\Core\Builders\Users;

use Coretik\App;

class CacheBuster
{
    const CACHE_KEY = 'app_users_cache_buster';

    public static function get(): string
    {
        return App::option(static::CACHE_KEY, '');
    }

    public static function set(string $hash)
    {
        App::instance()->option->set(static::CACHE_KEY, $hash, true);
    }
}
