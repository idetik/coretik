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
        if (-1 === $number) {
            $number = 0;
        }

        $this->set('number', $number);
        return $this;
    }

    public function childOf(int|array $values): self
    {
        if (is_array($values)) {
            $values = current($values);
        }
        $this->set('parent', $values);
        return $this;
    }

    /**
     * Alias of childOf
     */
    public function belongsTo(int $parent): self
    {
        $this->childOf($parent);
        return $this;
    }

    public function addContext(array $values, string $opt = 'in', string $context = '')
    {
        $values = $values ?: [0];
        switch ($opt) {
            case 'in':
                if (isset($this->include)) {
                    $values = array_intersect($this->include, $values);
                }
                $this->include = $values;
                break;
            case 'not_in':
                if (isset($this->exclude)) {
                    $values = array_unique(array_merge($this->exclude, $values));
                }
                $this->exclude = $values;
                break;
        }
        return $this;
    }

    public function withoutMetas(): self
    {
        $this->set('update_term_meta_cache', false);
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
