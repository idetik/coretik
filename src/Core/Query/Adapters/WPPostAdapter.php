<?php

namespace Coretik\Core\Query\Adapters;

use Coretik\Core\Query\Interfaces\TaxonomyClauseInterface;
use Coretik\Core\Query\Interfaces\MetaClauseInterface;
use Coretik\Core\Query\Interfaces\WhereClauseInterface;
use Coretik\Core\Utils\Arr;

class WPPostAdapter extends WPAdapter
{
    use Metable;
    use Nestable;

    const PARAMETERS = [
        'author',
        'author_name',
        'author__in',
        'author__not_in',
        'cat', // 'use minus to exclude'
        'category_name', // use comma or plus selectors
        'category__and',
        'category__in',
        'category__not_in',
        'tag',
        'tag_id',
        'tag__and',
        'tag__in',
        'tag__not_in',
        'tag_slug__and',
        'tag_slug__in',
        'tax_query',
        'p',
        'name',
        'title',
        'page_id',
        'pagename',
        'post_name__in',
        'post_parent',
        'post_parent__in',
        'post_parent__not_in',
        'post__in',
        'post__not_in',
        'has_password',
        'post_password',
        'post_type',
        'post_status',
        'comment_count',
        'posts_per_page',
        'nopaging',
        'paged',
        'posts_per_archive_page',
        'offset',
        'page',
        'ignore_sticky_posts',
        'order',
        'orderby',
        'year',
        'monthnum',
        'w',
        'day',
        'hour',
        'minute',
        'second',
        'm',
        'date_query',
        'meta_key',
        'meta_value',
        'meta_value_num',
        'meta_compare',
        'meta_query',
        'perm',
        'post_mime_type',
        'cache_results',
        'update_post_term_cache',
        'update_post_meta_cache',
        'no_found_rows',
        's',
        'exact',
        'sentence',
        'fields',
    ];

    protected $nestable = ['meta_query', 'tax_query'];

    public function query()
    {
        return new \WP_Query($this->getParameters());
    }

    public function limit(int $number): self
    {
        $this->set('posts_per_page', $number);
        return $this;
    }

    public function type(string $type): self
    {
        $this->set('post_type', $type);
        return $this;
    }

    public function page(int $page): self
    {
        $this->set('no_found_rows', false);
        $this->set('paged', $page ?: 1);
        return $this;
    }

    public function childOf(int|array $values): self
    {
        if (is_array($values)) {
            $this->set('post_parent__in', $values);
        } else {
            $this->set('post_parent', $values);
        }
        return $this;
    }

    public function addTaxQuery(array $tax_query, $relation = 'AND')
    {
        if (empty($this->tax_query)) {
            $this->tax_query = ['relation' => $relation];
        }
        if ('OR' === $relation && 'AND' === $this->tax_query['relation']) {
            $this->tax_query = [
                'relation' => 'OR',
                $this->tax_query,
            ];
        }

        $this->tax_query[] = $tax_query;
        return $this;
    }


    public function addTaxQueryGroup(array $tax_queries, $relation = 'OR')
    {
        $query_group = [];
        $query_group['relation'] = $relation;
        foreach ($tax_queries as $tax_query) {
            $query_group[] = $tax_query;
        }
        $this->addTaxQuery($query_group);
    }

    public function addContext(array $values, string $opt = 'in', string $context = 'post')
    {
        if (!in_array($context . '__' . $opt, static::PARAMETERS)) {
            throw new \Exception("Invalid contex : " . $context . "__" . $opt);
        }
        $values = $values ?: [0];
        if (isset($this->{$context . '__' . $opt})) {
            switch ($opt) {
                case 'in':
                    $values = array_intersect($this->{$context . '__' . $opt}, $values);
                    break;
                case 'not_in':
                default:
                    $values = array_unique(array_merge($this->{$context . '__' . $opt}, $values));
                    break;
            }
        }
        $this->{$context . '__' . $opt} = $values;
        return $this;
    }

    protected function resolveWhere(WhereClauseInterface $where, $relation)
    {
        switch (true) {
            case $where instanceof MetaClauseInterface:
                $this->addMetaQuery(Arr::whereValuesAreSet($where->toArray()), $relation);
                break;
            case $where instanceof TaxonomyClauseInterface:
                $this->addTaxQuery(Arr::whereValuesAreSet($where->toArray()), $relation);
                break;
        }
    }
}
