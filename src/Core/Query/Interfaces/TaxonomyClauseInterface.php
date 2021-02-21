<?php

namespace Coretik\Core\Query\Interfaces;

interface TaxonomyClauseInterface extends WhereClauseInterface
{
    public function children(): bool;
    public function field(): string;
}
