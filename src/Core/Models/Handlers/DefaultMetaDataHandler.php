<?php

namespace Coretik\Core\Models\Handlers;

use Coretik\Core\Builders\Handler;
use Coretik\Core\Builders\Interfaces\ModelableInterface;

use function Globalis\WP\Cubi\str_starts_with;

class DefaultMetaDataHandler extends Handler
{
    public function actions(): void
    {
        \add_filter("default_post_metadata", [$this, 'loadMeta'], 10, 5);
    }

    public function freeze(): void
    {
        \remove_filter('default_post_metadata', [$this, 'loadMeta']);
    }

    public function loadMeta($value, $object_id, $meta_key, $single, $meta_type)
    {
        if (str_starts_with($meta_key, '_')) {
            return $value;
        }

        if (!$this->builder instanceof ModelableInterface) {
            return $value;
        }

        if (!$this->builder->concern($object_id)) {
            return $value;
        }

        $model = $this->builder->model((int)$object_id);

        if ($model->hasMeta($meta_key) && !empty($model->metaDefinition($meta_key)?->defaultValue())) {
            $value = $model->metaDefinition($meta_key)->defaultValue();

            if (false === $single) {
                $value = [$value];
            }
        }
        return $value;
    }
}
