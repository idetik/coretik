<?php

namespace Coretik\Core\Query;

use Coretik\Core\Query\Adapters\WPTermAdapter as QueryAdapter;

class Term extends Query
{
    public function __construct(ModelableInterface $mediator)
    {
        parent::__construct(new QueryAdapter(), $mediator);
    }
}
