<?php

namespace Coretik\Core\Models\Adapters;

use Coretik\Core\Models\Interfaces\ModelInterface;

abstract class WPAdapter
{
    protected $model;

    public function __construct(ModelInterface $model)
    {
        $this->model = $model;
    }

    /**
     * Get a meta value, or $default when the meta doesn't exist.
     * Falsy stored values ("0", 0, "") are returned as is.
     */
    protected function readMeta(string $type, string $key, $default, bool $single)
    {
        if (!\metadata_exists($type, $this->model->id(), $key)) {
            return $default;
        }
        return \get_metadata($type, $this->model->id(), $key, $single);
    }

    /**
     * Add or update a meta using the type specific WP functions (update_{$type}_meta, add_{$type}_meta).
     * WP returns false when the value is unchanged: this is not considered as a failure.
     */
    protected function writeMeta(string $type, string $key, $value, bool $unique = false): void
    {
        $id = $this->model->id();

        if (\metadata_exists($type, $id, $key)) {
            $current = \get_metadata($type, $id, $key, true);
            $result = ('update_' . $type . '_meta')($id, $key, $value);
            $failed = false === $result && !$this->isSameMetaValue($current, $value);
        } else {
            $result = ('add_' . $type . '_meta')($id, $key, $value, $unique);
            $failed = false === $result;
        }

        if (\is_wp_error($result)) {
            throw new \RuntimeException("Update {$type} meta: failure - {$id} / {$key} : " . $result->get_error_message());
        }

        if ($failed) {
            throw new \RuntimeException("Update {$type} meta: failure - {$id} / {$key}");
        }
    }

    protected function isSameMetaValue($current, $value): bool
    {
        // Meta are stored as strings: compare their stored representation
        return (string)\maybe_serialize($current) === (string)\maybe_serialize($value);
    }

    /**
     * Throw if a WP insert / update function returned an error (false, 0 or WP_Error).
     */
    protected function assertSuccess($result, string $message): void
    {
        if (\is_wp_error($result)) {
            throw new \RuntimeException($message . ' : ' . $result->get_error_message());
        }
        if (empty($result)) {
            throw new \RuntimeException($message);
        }
    }
}
