<?php

namespace Coretik\Core\Query;

use Coretik\Core\Builders\Interfaces\ModelableInterface;
use Coretik\Core\Query\Interfaces\QuerierInterface;
use Coretik\Core\Query\Interfaces\QueryBuilderInterface;
use Coretik\Core\Query\Clause\TaxonomyClause;
use Coretik\Core\Query\Clause\MetaClause;
use Coretik\Core\Query\Clause\WhereClause;
use Coretik\Core\Query\Clause\DateClause;
use Coretik\Core\Collection;
use Coretik\App;

abstract class Query implements QuerierInterface
{
    protected $builder;
    protected $query;
    protected $mediator;
    protected $lastClause;

    abstract public function getQueryArgsDefault();
    abstract public function newQueryBuilderInstance();

    /**
     * @param ModelableInterface $mediator
     * @param QueryBuilderInterface|null $queryBuilder
     */
    public function __construct(ModelableInterface $mediator, $queryBuilder = null)
    {
        $this->mediator = $mediator;
        if (empty($queryBuilder)) {
            $queryBuilder = $this->newQueryBuilderInstance();
        }
        $this->builder = $queryBuilder;
    }

    public function where($where)
    {
        if (\func_num_args() > 1) {
            $where = new WhereClause(...\func_get_args());
        }
        $this->lastClause = \get_class($where);
        $this->builder->where($where);
        return $this;
    }

    public function orWhere($where)
    {
        $this->builder->orWhere($where);
    }

    public function or()
    {
        $last = $this->lastClause;
        $this->orWhere(new $last(...\func_get_args()));
        return $this;
    }

    public function and()
    {
        $last = $this->lastClause;
        $this->where(new $last(...\func_get_args()));
        return $this;
    }

    public function whereTax()
    {
        $this->where(new TaxonomyClause(...\func_get_args()));
        return $this;
    }

    public function whereMeta()
    {
        $this->where(new MetaClause(...\func_get_args()));
        return $this;
    }

    public function group(callable $group)
    {
        $newQuery = new static($this->mediator);
        $group($newQuery);
        $this->builder->nest($newQuery->builder());
        return $this;
    }

    public function __sleep()
    {
        return ['builder'];
    }

    protected function hash()
    {
        return md5(serialize($this));
    }

    public function run()
    {
        $hash = $this->hash();

        if (!$this->cache()->has($hash)) {
            $this->cache()->set($hash, $this->builder->query());
        }

        // if ($this->prefetch) {
            //\Globalis\WP\Cubi\mysql_prefetch_objects($queries[$hash]->posts, [], [], true);
        // }

        $this->query = $this->cache()->get($hash);
    }

    public function cache()
    {
        return QueryCache::instance();
    }

    /**
     * Getters
     */
    public function builder()
    {
        return $this->builder;
    }

    public function get()
    {
        if (empty($this->query)) {
            $this->run();
        }
        return $this->query;
    }

    public function results(): array
    {
        return $this->get()->posts;
    }

    public function pluck(string $col): array
    {
        return array_map(function ($row) use ($col) {
            return $row->$col;
        }, $this->results());
    }

    public function ids(): array
    {
        return $this->pluck('ID');
    }

    public function models(): \Generator
    {
        $results = $this->ids();
        foreach ($results as $result) {
            yield $this->mediator->model($result);
        }
    }

    public function collection(): Collection
    {
        return $this->collect($this->models());
    }

    protected function collect($data)
    {
        return new Collection($data);
    }

    /**
     * Force a clone of the underlying query builder when cloning.
     *
     * @return void
     */
    public function __clone()
    {
        $this->builder = clone $this->builder;
    }
}
