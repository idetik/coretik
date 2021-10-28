<?php

namespace Coretik\Core\Query\Adapters;

use Coretik\Core\Query\Interfaces\MetaClauseInterface;
use Coretik\Core\Query\Interfaces\WhereClauseInterface;
use Coretik\Core\Utils\Arr;

class WPCommentAdapter extends WPAdapter
{
    use Metable;
    use Nestable;

    const PARAMETERS = [
        'author_email',
        'author_url',
        'author__in',
        'author__not_in',
        'include_unapproved',
        'fields',
        'ID',
        'comment__in',
        'comment__not_in',
        'karma',
        'number',
        'offset',
        'no_found_rows',
        'orderby',
        'order',
        'parent',
        'parent__in',
        'parent__not_in',
        'post_author__in',
        'post_author__not_in',
        'post_id',
        'post__in',
        'post__not_in',
        'post_author',
        'post_name',
        'post_parent',
        'post_status',
        'post_type',
        'status',
        'type',
        'type__in',
        'type__not_in',
        'user_id',
        'search',
        'hierarchical',
        'count',
        'cache_domain',
        'meta_key',
        'meta_value',
        'meta_query',
        'date_query',
        'update_comment_meta_cache',
        'update_comment_post_cache',
    ];

    protected $nestable = ['meta_query'];

    public function query()
    {
        return new \WP_Comment_Query($this->getParameters());
    }

    public function limit(int $number): self
    {
        $this->set('number', $number);
        return $this;
    }

    public function page(int $page): self
    {
        $this->set('no_found_rows', false);
        $this->set('paged', $page ?: 1);
        return $this;
    }

    public function addContext(array $values, string $opt = 'in', string $context = 'comment')
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
        }
    }
}
