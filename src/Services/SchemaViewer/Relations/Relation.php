<?php

namespace Coretik\Services\SchemaViewer\Relations;

/**
 * A relation between two schema objects (post types, taxonomies, user and comment types)
 */
final class Relation
{
    const BELONGS_TO = 'belongsTo';
    const HAS_MANY = 'hasMany';
    const HAS_ONE = 'hasOne';
    const TAXONOMY = 'taxonomy';
    const PARENT = 'parent';

    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly string $type,
        public readonly string $detail = '',
    ) {
    }

    /**
     * The same relation seen from the other side: "A hasMany B" is "B belongsTo A"
     */
    public function key(): string
    {
        return match ($this->type) {
            self::HAS_MANY, self::HAS_ONE => $this->to . '>' . $this->from,
            default => $this->from . '>' . $this->to,
        };
    }

    public function label(): string
    {
        return '' === $this->detail ? $this->type : $this->type . ' (' . $this->detail . ')';
    }
}
