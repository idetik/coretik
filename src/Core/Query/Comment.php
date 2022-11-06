<?php

namespace Coretik\Core\Query;

use Coretik\Core\Query\Adapters\WPCommentAdapter as QueryAdapter;

class Comment extends Query
{
    const PRIMARY_KEY = 'comment_ID';

    public function newQueryBuilderInstance(array $defaultArgs = [])
    {
        $args = \array_merge($this->getQueryArgsDefault(), $defaultArgs);
        return new QueryAdapter($args);
    }

    public function results(): array
    {
        return $this->get()->comments;
    }

    public function getQueryArgsDefault()
    {
        return [
            'orderby' => 'comment_date_gmt',
            'order' => 'ASC',
            'hierarchical' => 'threaded',
            'status' => 'approve',
        ];
    }

    public function statusNotTrashed()
    {
        return [
            "approve",
            "hold",
        ];
    }
}
