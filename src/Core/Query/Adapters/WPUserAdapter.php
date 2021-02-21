<?php

namespace Coretik\Core\Query\Adapters;

use Coretik\Core\Query\Interfaces\MetaClauseInterface;
use Coretik\Core\Query\Interfaces\WhereClauseInterface;
use Coretik\Core\Utils\Arr;

class WPUserAdapter extends WPAdapter
{
    use Metable;
    use Nestable;

    const PARAMETERS = [
        'blog_id',
        'role',
        'role__in',
        'role__not_in',
        'meta_key',
        'meta_value',
        'meta_compare',
        'meta_query',
        'include',
        'exclude',
        'search',
        'search_columns',
        'orderby',
        'order',
        'offset',
        'number',
        'paged',
        'count_total',
        'fields',
        'who',
        'has_published_posts',
        'nicename',
        'nicename__in',
        'nicename__not_in',
        'login',
        'login__in',
        'login__not_in',
    ];

    protected $nestable = ['meta_query'];

    public function query()
    {
        return new \WP_User_Query($this->getParameters());
    }

    public function limit(int $number): self
    {
        $this->set('number', $number);
        return $this;
    }

    public function page(int $page): self
    {
        $this->set('paged', $page ?: 1);
        return $this;
    }

    protected function resolveWhere(WhereClauseInterface $where, $relation)
    {
        switch (true) {
            case $where instanceof MetaClauseInterface:
                $this->addMetaQuery(Arr::whereValuesAreSet($where->toArray()), $relation);
                break;
        }
    }
}
