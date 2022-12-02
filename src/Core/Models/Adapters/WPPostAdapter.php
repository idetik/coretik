<?php

namespace Coretik\Core\Models\Adapters;

use Coretik\Core\Models\Interfaces\MetableAdapterInterface;
use Coretik\Core\Models\Interfaces\CRUDInterface;

class WPPostAdapter extends WPAdapter implements MetableAdapterInterface, CRUDInterface
{
    public function meta(string $key, $default = false, bool $single = true)
    {
        $meta = \get_post_meta($this->model->id(), $key, $single);

        if ($single && '' === $meta) {
            return $default;
        }

        if (!$single && [] === $meta) {
            return $default;
        }

        return $meta;
    }

    public function updateMeta(string $key, $value, bool $unique = false)
    {
        if (false !== $this->meta($key)) {
            $success = \update_post_meta($this->model->id(), $key, $value);
        } else {
            $success = \add_post_meta($this->model->id(), $key, $value, $unique);
        }
        if (!$success) {
            throw new \RuntimeException("Update post meta: failure - {$this->model->id()} / {$key}");
        }
    }

    public function deleteMeta(string $key, $value = '')
    {
        if (!\delete_post_meta($this->model->id(), $key, $value)) {
            throw new \RuntimeException("Delete post meta: failure - {$this->model->id()} / {$key}");
        }
    }

    public function create(array $args = [])
    {
        $post_id = \wp_insert_post($args);
        if (!$post_id) {
            throw new \RuntimeException("Insert post: failure");
        }
        return $post_id;
    }

    public function get($post = null, string $output = 'OBJECT', string $filter = 'raw')
    {
        $wp_result = \get_post($post, $output, $filter);
        if (empty($wp_result)) {
            throw new \RuntimeException("Get post: failure - {$post}");
        }
        return $wp_result;
    }

    public function update(array $args = [])
    {
        $args['ID'] = $this->model->id();
        $post_id = \wp_update_post($args);
        if (!$post_id) {
            throw new \RuntimeException("Update post: failure - {$post}");
        }
    }

    public function delete(bool $force_delete = false)
    {
        $delete = \wp_delete_post($this->model->id(), $force_delete);
        if (empty($delete) || false === $delete) {
            throw new \RuntimeException("Deleting post: failure - {$this->model->id()}");
        }
    }
}
