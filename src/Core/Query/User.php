<?php

namespace Coretik\Core\Query;

use Coretik\Core\Query\Adapters\WPUserAdapter as QueryAdapter;

class User extends Query
{
    public function __construct(ModelableInterface $mediator)
    {
        parent::__construct(new QueryAdapter(), $mediator);
    }
}
