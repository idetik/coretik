<?php

namespace Coretik\Core\Query\Adapters;

trait Metable
{
    public function addMetaQuery(array $meta_query, $relation = 'AND')
    {
        if (empty($this->meta_query)) {
            $this->meta_query = ['relation' => $relation];
        }
        if ('OR' === $relation && 'AND' === $this->meta_query['relation']) {
            $this->meta_query = [
                'relation' => 'OR',
                $this->meta_query,
            ];
        }

        $this->meta_query[] = $meta_query;
        return $this;
    }

    public function addMetaQueryGroup(array $meta_queries, $relation = 'OR')
    {
        $query_group = [];
        $query_group['relation'] = $relation;
        foreach ($meta_queries as $meta_query) {
            $query_group[] = $meta_query;
        }
        $this->addMetaQuery($query_group);
    }
}
