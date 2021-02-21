<?php

namespace Coretik\Core\Query\Interfaces;

interface WhereClauseInterface
{
    public function key(): string;
    public function value();
    public function compare(): string;
}
