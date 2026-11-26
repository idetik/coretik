<?php

namespace Coretik\Core\Query\Interfaces;

use Coretik\Core\Collection;

interface QuerierInterface
{
    public function getQueryArgsDefault();
    public function newQueryBuilderInstance();
    public function results(): array;
    public function run();

    // Clauses
    public function where($where);
    public function orWhere($where);
    public function whereMeta(...$args);
    public function whereTax(...$args);

    // Query parameters
    public function set(string $key, $value): static;
    public function childOf(int|array $values): static;
    public function all(): static;
    public function limit(int $number): static;
    public function in(array $ids): static;
    public function notIn(array $ids): static;
    public function not(int $id): static;

    // Results
    public function querier();
    public function get();
    public function ids(): array;
    public function models(): \Generator;
    public function collection($models = true): Collection;
    public function first($model = true);
    public function count(): int;
    public function total(): int;
}
