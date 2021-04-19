<?php

namespace Coretik\Core\Query;

use Coretik\Core\Query\Adapters\WPPostAdapter as QueryAdapter;
use Coretik\Core\Builders\Interfaces\ModelableInterface;
use Globalis\WP\Cubi;

class Post extends Query
{
    const PRIMARY_KEY = 'ID';

    public function newQueryBuilderInstance(array $defaultArgs = [])
    {
        $args = \array_merge($this->getQueryArgsDefault(), $defaultArgs);
        return new QueryAdapter($args);
    }

    public function getQueryArgsDefault()
    {
        return [
            'post_status' => Cubi\is_frontend() ? 'publish' : $this->statusNotTrashed(),
            'orderby' => 'post_date',
            'order' => 'DESC',
            'post_type' => $this->mediator->getName()
        ];
    }

    public function statusNotTrashed()
    {
        return [
            "publish",
            "future",
            "draft",
            "pending",
            "private",
            "request-pending",
            "request-confirmed",
            "request-completed",
        ];
    }
}
