<?php

namespace Coretik\Tests\Unit\SchemaViewer\Fixtures;

use Coretik\Core\Builders\BuilderModelable;
use Coretik\Core\Collection;

/**
 * Builder of the schema viewer tests: name, type, args, model class and taxonomy object types are given
 */
class FakeBuilder extends BuilderModelable
{
    public function __construct(
        private string $name,
        private string $type,
        private array $args = [],
        ?string $modelClass = null,
        private array $objectTypes = []
    ) {
        parent::__construct();
        if (null !== $modelClass) {
            $this->factory(fn () => (new \ReflectionClass($modelClass))->newInstanceWithoutConstructor());
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function args(): Collection
    {
        return new Collection($this->args);
    }

    public function getObjectTypes(): array
    {
        return $this->objectTypes;
    }

    public function wpObject(int $id)
    {
        return null;
    }

    public function concern(int $objectId): bool
    {
        return false;
    }
}
