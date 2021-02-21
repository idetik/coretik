<?php

namespace Coretik\Core\Query\Interfaces;

interface MetaClauseInterface extends WhereClauseInterface
{
    public function type(): string;
}
