<?php

namespace Coretik\Core\Models\Adapters;

use Coretik\Core\Models\Interfaces\MetableAdapterInterface;
use Coretik\Core\Models\Interfaces\CRUDInterface;

class WPTermAdapter extends WPAdapter implements MetableAdapterInterface, CRUDInterface
{
    public function meta(string $key, $default = false, bool $single = true)
    {
        return \get_term_meta($this->model->id(), $key, $single) ?: $default;
    }

    public function updateMeta(string $key, $value, bool $unique = false)
    {
        if (false !== $this->meta($key)) {
            $success = \update_term_meta($this->model->id(), $key, $value);
        } else {
            $success = \add_term_meta($this->model->id(), $key, $value, $unique);
        }
        if (\is_wp_error($success)) {
            throw new \RuntimeException("Update term meta: failure - {$this->model->id()} / {$key} : " . $success->get_error_message());
        }
    }

    public function deleteMeta(string $key, $value = '')
    {
        if (!\delete_term_meta($this->model->id(), $key, $value)) {
            throw new \RuntimeException("Delete term meta: failure - {$this->model->id()} / {$key}");
        }
    }

    public function get($term = null, string $taxonomy = '', string $output = 'OBJECT', string $filter = 'raw')
    {
        $wp_result = \get_term($term, $taxonomy, $output, $filter);
        if (empty($wp_result)) {
            throw new \RuntimeException("Get term: failure - {$term}");
        }
        return $wp_result;
    }

    public function delete()
    {
        return \wp_delete_term($this->model->id(), $this->model->name());
    }

    public function create(array $args = [])
    {
        $term_ids = \wp_insert_term($this->model->title(), $this->model->name(), $args);
        if (!is_array($term_ids)) {
            throw new \RuntimeException("Insert term: failure");
        }
        return $term_ids['term_id'];
    }

    public function update(array $args = [])
    {
        $term_ids = \wp_update_term($this->model->id(), $this->model->name(), $args);
        if (!is_array($term_ids)) {
            throw new \RuntimeException("Update term: failure");
        }
        return $term_ids;
    }
}
