<?php

namespace Coretik\Services\Notices;

use Coretik\App;

class Storage implements StorageInterface
{
    const OPTION_KEY = '_app_notices';

    // todo user_id, userloggedin()
    // @todo user_meta
    // @todo session
    public function get(): \ArrayIterator
    {
        return App::option(static::OPTION_KEY, new \ArrayIterator([]));
    }

    public function set(\ArrayIterator $iterator)
    {
        App::instance()->option->set(static::OPTION_KEY, $iterator, true);
    }
}
