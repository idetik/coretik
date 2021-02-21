<?php

namespace Coretik\Core\Query\Clause;

use Coretik\Core\Query\Interfaces\WhereClauseInterface;

class WhereClause implements WhereClauseInterface
{
    protected $key;
    protected $value;
    protected $compare;

    public function __construct(string $key, $value, string $compare = '=')
    {
        $this->key = $key;
        $this->value = $value;
        $this->compare = $compare;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function value()
    {
        return $this->value;
    }

    public function compare(): string
    {
        return $this->compare;
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key(),
            'compare' => $this->compare(),
            'value' => $this->value()
        ];
    }
}
