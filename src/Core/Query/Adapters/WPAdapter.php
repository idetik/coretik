<?php

namespace Coretik\Core\Query\Adapters;

use Coretik\Core\Query\Interfaces\WhereClauseInterface;
use Coretik\Core\Query\Interfaces\QueryBuilderInterface;

abstract class WPAdapter implements QueryBuilderInterface
{
    const PARAMETERS = [];

    abstract protected function resolveWhere(WhereClauseInterface $where, $relation);

    public function __construct(array $defaultArgs = [])
    {
        foreach ($defaultArgs as $key => $val) {
            $this->set($key, $val);
        }
    }

    public function getParameters(): array
    {
        $parameters = [];
        foreach (static::PARAMETERS as $key) {
            if (\property_exists($this, $key)) {
                $parameters[$key] = $this->$key;
            }
        }
        return $parameters;
    }

    public function where(WhereClauseInterface $where): self
    {
        $this->resolveWhere($where, 'AND');
        return $this;
    }

    public function orWhere(WhereClauseInterface $where): self
    {
        $this->resolveWhere($where, 'OR');
        return $this;
    }

    public function addContext(string $context, array $values, $opt = 'in')
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

    public function all(): self
    {
        $this->limit(-1);
        return $this;
    }

    public function not(int $id)
    {
        $this->notIn([$id]);
        return $this;
    }

    public function notIn(array $ids)
    {
        $this->addContext($ids, 'not_in');
        return $this;
    }

    public function in(array $ids)
    {
        $this->addContext($ids);
        return $this;
    }

    public function get(string $key)
    {
        if (\in_array($key, static::PARAMETERS)) {
            return $this->$key ?? null;
        }
        throw new \RuntimeException("Undefined paramaters {$key}");
    }

    public function set(string $key, $val)
    {
        if (\in_array($key, static::PARAMETERS)) {
            $this->$key = $val;
        }
    }

    public function __set($key, $val)
    {
        $this->set($key, $val);
    }

    public function __sleep()
    {
        return \array_keys($this->getParameters());
    }
}
