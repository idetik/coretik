<?php

namespace Coretik\Core\Models\Handlers\Relationship;

use Coretik\Core\Builders\Interfaces\BuilderInterface;

/**
 * Handlers mounting a "belongs to" relation from a pivot (meta key or callable)
 */
interface BelongsToRelationInterface
{
    /**
     * The builder the object belongs to, null when it is not set
     */
    public function belongsToBuilder(): ?BuilderInterface;

    /**
     * The meta key containing the parent id, or the callable returning it
     */
    public function pivot(): string|callable;
}
