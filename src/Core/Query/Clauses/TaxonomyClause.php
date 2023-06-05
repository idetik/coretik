<?php

namespace Coretik\Core\Query\Clauses;

use Coretik\Core\Query\Interfaces\TaxonomyClauseInterface;
use Coretik\Core\Query\Clauses\WhereClause;

class TaxonomyClause extends WhereClause implements TaxonomyClauseInterface
{
    protected $field;
    protected $children;

    public function __construct(string $taxonomy, $terms, string $compare = 'IN', string $field = 'term_id', bool $include_children = true)
    {
        parent::__construct($taxonomy, $terms, $compare);
        $this->field = $field;
        $this->children = $include_children;
    }

    public function children(): bool
    {
        return $this->children;
    }

    public function field(): string
    {
        return $this->field;
    }

    public function toArray(): array
    {
        return [
            'taxonomy' => $this->key(),
            'field' => $this->field(),
            'terms' => $this->value(),
            'operator' => $this->compare(),
            'include_children' => $this->children(),
        ];
    }
}
