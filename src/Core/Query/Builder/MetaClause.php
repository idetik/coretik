<?php

namespace Coretik\Core\Query\Clause;

use Coretik\Core\Query\Interfaces\MetaClauseInterface;

class MetaClause extends WhereClause implements MetaClauseInterface
{
    protected $name;
    protected $type;

    public function __construct(string $key, $value, string $compare = '=', string $type = 'CHAR', $name = null)
    {
        parent::__construct($key, $value, $compare);
        $this->type = $type;
        $this->name = $name;
    }

    public function name()
    {
        return $this->name;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function toArray(): array
    {
        return parent::toArray() + ['type' => $this->type(), 'name' => $this->name()];
    }
}
