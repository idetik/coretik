<?php

namespace Coretik\Core\Models\Handlers;

use Coretik\Core\Builders\Handler;
use Coretik\Core\Builders\Interfaces\ModelableInterface;

/**
 * Prevent protected metas from being added, updated or deleted outside of the model.
 * Attached to every modelable builder when it is registered in the schema.
 */
class Guard extends Handler
{
    const META_TYPES = [
        'post' => 'post',
        'user' => 'user',
        'taxonomy' => 'term',
        'comment' => 'comment',
    ];

    /**
     * Meta keys (database keys) declared as protected by the builder models, null until resolved.
     */
    protected ?array $protectedKeys = null;

    public function actions(): void
    {
        foreach ($this->hooks() as $hook) {
            \add_filter($hook, [$this, 'guard'], 10, 5);
        }
    }

    public function freeze(): void
    {
        foreach ($this->hooks() as $hook) {
            \remove_filter($hook, [$this, 'guard']);
        }
    }

    /**
     * Only the metadata of the builder object type are guarded: a post id is not a user id.
     */
    protected function hooks(): array
    {
        $type = static::META_TYPES[$this->builder->getType()] ?? null;
        if (empty($type)) {
            return [];
        }

        return [
            "add_{$type}_metadata",
            "update_{$type}_metadata",
            "delete_{$type}_metadata",
        ];
    }

    public function guard($check, $object_id, $meta_key, $meta_value, $extra = null)
    {
        if (!$this->builder instanceof ModelableInterface) {
            return $check;
        }

        // Cheap check first: avoid loading a model for every meta write
        if (!\in_array($meta_key, $this->protectedKeys(), true)) {
            return $check;
        }

        if (!$this->builder->concern((int)$object_id)) {
            return $check;
        }

        $model = $this->builder->model((int)$object_id);

        if (\method_exists($model, 'isProtectedMeta') && $model->isProtectedMeta($meta_key)) {
            // Short-circuit the write, as if it succeeded
            return true;
        }

        return $check;
    }

    protected function protectedKeys(): array
    {
        if (null === $this->protectedKeys) {
            $model = $this->builder->model();
            $this->protectedKeys = \method_exists($model, 'protectedMetaKeys') ? \array_values($model->protectedMetaKeys(false)) : [];
        }
        return $this->protectedKeys;
    }
}
