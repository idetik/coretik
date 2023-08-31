<?php

namespace Coretik\Core\Builders\Traits;

trait Maker
{
    public static function make(): self
    {
        return new static(...func_get_args());
    }

    public function getBuilder(): self
    {
        return $this;
    }

    public function addToSchema($schema = null): self
    {
        $schema = $schema ?? \app()->schema();
        $schema->register($this);
        return $this;
    }
}
