<?php

namespace Coretik\Core\Query;

use Coretik\Core\Query\Adapters\WPUserAdapter as QueryAdapter;

class User extends Query
{
    const PRIMARY_KEY = 'ID';

    public function newQueryBuilderInstance(array $defaultArgs = [])
    {
        $args = \array_merge($this->getQueryArgsDefault(), $defaultArgs);
        return new QueryAdapter($args);
    }

    public function getQueryArgsDefault()
    {
        return [];
    }
}
