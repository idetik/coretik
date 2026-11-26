<?php

namespace Coretik\Core\Models\Adapters;

use Coretik\Core\Models\Interfaces\MetableAdapterInterface;
use Coretik\Core\Models\Interfaces\CRUDInterface;

class WPUserAdapter extends WPAdapter implements MetableAdapterInterface, CRUDInterface
{
    public function meta(string $key, $default = false, bool $single = true)
    {
        return $this->readMeta('user', $key, $default, $single);
    }

    public function updateMeta(string $key, $value, bool $unique = false)
    {
        $this->writeMeta('user', $key, $value, $unique);
    }

    public function deleteMeta(string $key, $value = '')
    {
        if (!\delete_user_meta($this->model->id(), $key, $value)) {
            throw new \RuntimeException("Delete user meta: failure - {$this->model->id()} / {$key}");
        }
    }

    public function create(array $args = [])
    {
        $user_id = \wp_insert_user($args);
        $this->assertSuccess($user_id, "Insert user: failure");
        return $user_id;
    }

    public function get($user = null, string $field = 'ID')
    {
        $wp_result = \get_user_by($field, $user);
        if (empty($wp_result)) {
            throw new \RuntimeException("Get user: failure - {$user}");
        }
        return $wp_result;
    }

    public function update(array $args = [])
    {
        $args['ID'] = $this->model->id();
        $user_id = \wp_update_user($args);
        $this->assertSuccess($user_id, "Update user: failure - {$this->model->id()}");
        return $user_id;
    }

    public function delete(int $reassign = 0)
    {
        $delete = \wp_delete_user($this->model->id(), $reassign ?: null);
        if (empty($delete)) {
            throw new \RuntimeException("Deleting post: failure - {$this->model->id()}");
        }
        return true;
    }
}
