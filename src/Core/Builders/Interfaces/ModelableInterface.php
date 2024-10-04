<?php

namespace Coretik\Core\Builders\Interfaces;

use Coretik\Core\Models\Model;
use Coretik\Core\Query\Interfaces\QuerierInterface;

interface ModelableInterface
{
    public function factory(callable $factory);
    public function model($id = null, $initializer = null, bool $refresh = false): Model;
    public function querier(callable $querier);
    public function query(): QuerierInterface;
    public function wpObject(int $id);
    public function hasFactory(): bool;
    public function hasQuerier(): bool;
    public function concern(int $objectId): bool;
}
