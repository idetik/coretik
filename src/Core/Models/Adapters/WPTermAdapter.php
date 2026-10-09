<?php

namespace Coretik\Core\Models\Adapters;

use Coretik\Core\Models\Interfaces\MetableAdapterInterface;
use Coretik\Core\Models\Interfaces\CRUDInterface;

class WPTermAdapter extends WPAdapter implements MetableAdapterInterface, CRUDInterface
{
    public function meta(string $key, $default = false, bool $single = true)
    {
        return $this->readMeta('term', $key, $default, $single);
    }

    public function updateMeta(string $key, $value, bool $unique = false)
    {
        $this->writeMeta('term', $key, $value, $unique);
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
