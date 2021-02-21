<?php

namespace Coretik\Core\Query\Interfaces;

interface DateClauseInterface extends WhereClauseInterface
{
    public function column(): string;
    public function inclusive(): bool;
    public function year(): int;
    public function month(): int;
    public function week(): int;
    public function day(): int;
    public function hour(): int;
    public function minute(): int;
    public function second(): int;
    public function after();
    public function before();
}
