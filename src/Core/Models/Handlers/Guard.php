<?php

namespace Coretik\Core\Models\Handlers;

use Coretik\Core\Builders\Interfaces\BuilderInterface;
use Coretik\Core\Builders\Interfaces\HandlerInterface;
use Coretik\Core\Models\Interfaces\MetableAdapterInterface;
use Coretik\Core\Utils\Classes;

class Guard implements HandlerInterface
{
    private $builder;

    public function handle(BuilderInterface $builder): void
    {
        $this->builder = $builder;
        \add_filter('update_post_metadata', [$this, 'guard'], 10, 5);
        // \add_filter('update_user_metadata', [$this, 'guard'], 10, 5);
        \add_filter('update_term_metadata', [$this, 'guard'], 10, 5);
    }

    public function freeze(): void
    {
        \remove_filter('update_post_metadata', [$this, 'guard']);
        // \remove_filter('update_user_metadata', [$this, 'guard']);
        \remove_filter('update_term_metadata', [$this, 'guard']);
    }

    public function guard($check, $object_id, $meta_key, $meta_value, $prev_value)
    {
        if (!$this->builder->concern($object_id)) {
            return $check;
        }

        $model = $this->builder->model((int)$object_id);

        if (\in_array('Coretik\Core\Models\Traits\Metable', Classes::classUsesDeep($model))) {
            $check = $model->isProtectedMeta($meta_key) ?: null;
        }
        return $check;
    }
}
