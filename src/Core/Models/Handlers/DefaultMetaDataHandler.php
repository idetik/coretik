<?php

namespace Coretik\Core\Models\Handlers;

use Coretik\Core\Models\Interfaces\MetableInterface;
use Coretik\Core\Builders\Handler;
use Coretik\Core\Builders\Interfaces\ModelableInterface;

use function Globalis\WP\Cubi\str_starts_with;

/**
 * Default values of the declared metas (MetaDefinition::setDefaultValue()), returned by get_metadata() when the meta
 * does not exist, for the objects of the builder only.
 */
class DefaultMetaDataHandler extends Handler
{
    /** @var array<string, true> objects whose model is being built, "type:id" */
    protected static array $loading = [];

    public function actions(): void
    {
        \add_filter($this->hook(), [$this, 'loadMeta'], 10, 5);
    }

    public function freeze(): void
    {
        \remove_filter($this->hook(), [$this, 'loadMeta']);
    }

    /**
     * Filter of the builder meta type: a user builder never answers for a post meta (its concern() would look for a
     * user with the post id, a query each time since missing users are not cached)
     */
    public function hook(): string
    {
        $type = match ($this->builder->getType()) {
            'user' => 'user',
            'taxonomy' => 'term',
            'comment' => 'comment',
            default => 'post',
        };

        return "default_{$type}_metadata";
    }

    public function loadMeta($value, $object_id, $meta_key, $single, $meta_type)
    {
        if (str_starts_with($meta_key, '_')) {
            return $value;
        }

        if (!$this->builder instanceof ModelableInterface) {
            return $value;
        }

        // Object being resolved: the user (WP_User reads its capabilities) or model being built reads its own metas,
        // no default value meanwhile
        $loading = $meta_type . ':' . $object_id;
        if (isset(static::$loading[$loading])) {
            return $value;
        }

        static::$loading[$loading] = true;
        try {
            $model = $this->builder->concern((int)$object_id) ? $this->builder->model((int)$object_id) : null;
        } finally {
            unset(static::$loading[$loading]);
        }

        if ($model instanceof MetableInterface && $model->hasMeta($meta_key) && !empty($model->metaDefinition($meta_key)?->defaultValue())) {
            $value = $model->metaDefinition($meta_key)->defaultValue();

            if (false === $single) {
                $value = [$value];
            }
        }
        return $value;
    }
}
