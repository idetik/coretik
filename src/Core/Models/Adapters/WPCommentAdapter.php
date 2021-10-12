<?php

namespace Coretik\Core\Models\Adapters;

use Coretik\Core\Models\Interfaces\MetableAdapterInterface;
use Coretik\Core\Models\Interfaces\CRUDInterface;

class WPCommentAdapter extends WPAdapter implements MetableAdapterInterface, CRUDInterface
{

    public function meta(string $key, $default = false, bool $single = true)
    {
        return \get_comment_meta($this->model->id(), $key, $single) ?: $default;
    }

    public function updateMeta(string $key, $value, bool $unique = false)
    {
        if (false !== $this->meta($key)) {
            $success = \update_comment_meta($this->model->id(), $key, $value);
        } else {
            $success = \add_comment_meta($this->model->id(), $key, $value, $unique);
        }
        if (!$success) {
            throw new \RuntimeException("Update comment meta: failure - {$this->model->id()} / {$key}");
        }
    }

    public function deleteMeta(string $key, $value = '')
    {
        if (!\delete_comment_meta($this->model->id(), $key, $value)) {
            throw new \RuntimeException("Delete comment meta: failure - {$this->model->id()} / {$key}");
        }
    }

    public function create(array $args = [])
    {
        $comment_id = \wp_insert_comment($args);
        if (!$comment_id) {
            throw new \RuntimeException("Insert comment: failure");
        }
        return $comment_id;
    }

    public function get($comment = null, string $output = 'OBJECT')
    {
        $wp_result = \get_comment($comment, $output);
        if (empty($wp_result)) {
            throw new \RuntimeException("Get comment: failure - {$user}");
        }
        return $wp_result;
    }

    public function update(array $args = [])
    {
        $args['comment_ID'] = $this->id();
        $comment_id = \wp_update_comment($args);
        if (!$comment_id) {
            throw new \RuntimeException("Update comment: failure - {$comment_id}");
        }
        return $comment_id;
    }

    public function delete(bool $force_delete = false)
    {
        $delete = \wp_delete_comment($this->model->id(), $force_delete);
        if (empty($delete) || false === $delete) {
            throw new \RuntimeException("Deleting comment: failure - {$this->model->id()}");
        }
        return true;
    }
}
