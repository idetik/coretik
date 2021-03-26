<?php

namespace Coretik\Core\Query;

use Coretik\Core\Query\Adapters\WPTermAdapter as QueryAdapter;

class Term extends Query
{
    public function newQueryBuilderInstance(array $defaultArgs = [])
    {
        $args = \array_merge($this->getQueryArgsDefault(), $defaultArgs);
        return new QueryAdapter($args);
    }

    public function getQueryArgsDefault()
    {
        return [
            'taxonomy' => $this->mediator->getName()
        ];
    }
}
