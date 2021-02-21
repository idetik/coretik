<?php

namespace Coretik\Core\Builders\Interfaces;

use Coretik\Core\Models\Model;
use Coretik\Core\Query\Interfaces\QuerierInterface;

interface ModelableInterface
{
    public function factory(callable $factory);
    public function model($initializer): Model;
    public function querier(callable $querier);
    public function newQuery(): QuerierInterface;
    public static function query(): QuerierInterface;
    public function wpObject(int $id);
    public function hasFactory(): bool;
    public function hasQuerier(): bool;
}
