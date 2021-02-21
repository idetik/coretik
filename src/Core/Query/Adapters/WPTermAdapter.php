<?php

namespace Coretik\Core\Query\Adapters;

use Coretik\Core\Query\Interfaces\MetaClauseInterface;
use Coretik\Core\Query\Interfaces\WhereClauseInterface;
use Coretik\Core\Utils\Arr;

class WPTermAdapter extends WPAdapter
{
    use Metable;
    use Nestable;

    const PARAMETERS = [
        'taxonomy',
        'object_ids',
        'orderby',
        'order',
        'hide_empty',
        'include',
        'exclude',
        'exclude_tree',
        'number',
        'offset',
        'fields',
        'count',
        'name',
        'slug',
        'term_taxonomy_id',
        'hierarchical',
        'search',
        'name__like',
        'description__like',
        'pad_counts',
        'get',
        'child_of',
        'parent',
        'childless',
        'cache_domain',
        'update_term_meta_cache',
        'meta_query',
        'meta_key',
        'meta_value',
        'meta_type',
        'meta_compare',
    ];

    protected $nestable = ['meta_query'];

    public function query()
    {
        return new \WP_Term_Query($this->getParameters());
    }

    public function limit(int $number): self
    {
        $this->set('number', $number);
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
