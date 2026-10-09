<?php

namespace Coretik\Core\Models\Adapters;

use Coretik\Core\Models\Interfaces\MetableAdapterInterface;
use Coretik\Core\Models\Interfaces\CRUDInterface;

class WPCommentAdapter extends WPAdapter implements MetableAdapterInterface, CRUDInterface
{
    public function meta(string $key, $default = false, bool $single = true)
    {
        return $this->readMeta('comment', $key, $default, $single);
    }

    public function updateMeta(string $key, $value, bool $unique = false)
    {
        $this->writeMeta('comment', $key, $value, $unique);
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
        $this->assertSuccess($comment_id, "Insert comment: failure");
        return $comment_id;
    }

    public function get($comment = null, string $output = 'OBJECT')
    {
        $wp_result = \get_comment($comment, $output);
        if (empty($wp_result)) {
            throw new \RuntimeException("Get comment: failure - {$comment}");
        }
        return $wp_result;
    }

    public function update(array $args = [])
    {
        $args['comment_ID'] = $this->model->id();
        $result = \wp_update_comment($args);
        // wp_update_comment returns 0 when nothing changed: only false / WP_Error are failures
        if (false === $result || \is_wp_error($result)) {
            $this->assertSuccess($result, "Update comment: failure - {$this->model->id()}");
        }
        return $this->model->id();
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
