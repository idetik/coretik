<?php

namespace Coretik\Core;

use ArrayIterator;
use Coretik\Core\Interfaces\CollectionInterface;

class Collection implements CollectionInterface
{
    /**
     * The source data
     *
     * @var array
     */
    protected $data = [];

    /**
     * @param array $items Pre-populate collection with this key-value array
     */
    public function __construct($items = [])
    {
        $this->replace($items);
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value)
    {
        $this->data[$key] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, $default = null)
    {
        return $this->has($key) ? $this->data[$key] : $default;
    }

    /**
     * {@inheritdoc}
     */
    public function replace($items)
    {
        foreach ($items as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function first()
    {
        return $this->data[0];
    }

    /**
     * {@inheritdoc}
     */
    public function last()
    {
        $last = end($this->data);
        reset($this->data);
        return $last;
    }

    /**
     * {@inheritdoc}
     */
    public function all()
    {
        return $this->data;
    }

    /**
     * Get collection keys
     *
     * @return array The collection's source data keys
     */
    public function keys()
    {
        return \array_keys($this->data);
    }

    /**
     * {@inheritdoc}
     */
    public function has($key)
    {
        return \array_key_exists($key, $this->data);
    }

    /**
     * {@inheritdoc}
     */
    public function keyOf($value, $strict = false)
    {
        if (!\is_callable($value)) {
            return \array_search($value, $this->data, $strict);
        }

        foreach ($this->data as $key => $item) {
            if (\call_user_func($value, $item, $key)) {
                return $key;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function map(callable $callback)
    {
        $keys = array_keys($this->data);

        $items = array_map($callback, $this->data, $keys);

        return new static(array_combine($keys, $items));
    }

    /**
     * {@inheritdoc}
     */
    public function filter(callable $callback = null)
    {
        if ($callback) {
            return new static(\array_filter($this->data, $callback, ARRAY_FILTER_USE_BOTH));
        }

        return new static(\array_filter($this->data));
    }

    /**
     * {@inheritdoc}
     */
    public function groupBy(callable $group_by, bool $hide_empty = false)
    {
        $groups = [];
        foreach ($this->data as $object) {
            $group_key = \call_user_func($group_by, $object, $groups);
            if (!empty($group_key) && $group_key) {
                $groups[$group_key][] = $object;
            } elseif (!$hide_empty) {
                $groups['_others'][] = $object;
            }
        }
        ksort($groups);
        return new static($groups);
    }

    /**
     * {@inheritdoc}
     */
    public function makeTree(string $parentKey, string $primaryKey): array
    {
        $data = $this->data;
        $groups = [];
        foreach ($this->data as $object) {
            $groups[$object[$parentKey]][] = $object;
        }

        if (empty($groups)) {
            return [];
        }

        return $this->createTree($groups, $groups[0], $primaryKey);
    }

    protected function createTree(&$list, $parent, $primaryKey)
    {
        $tree = [];
        foreach ($parent as $k => $l) {
            if (isset($list[$l[$primaryKey]])) {
                $l['children'] = $this->createTree($list, $list[$l[$primaryKey]], $primaryKey);
            }
            $tree[] = $l;
        }
        return $tree;
    }

    /**
     * {@inheritdoc}
     */
    public function remove($key)
    {
        unset($this->data[$key]);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->data = [];
    }

    /**
     * Does this collection have a given key?
     *
     * @param  string $key The data key
     *
     * @return bool
     */
    public function offsetExists($key)
    {
        return $this->has($key);
    }

    /**
     * Get collection item for key
     *
     * @param string $key The data key
     *
     * @return mixed The key's value, or the default value
     */
    public function offsetGet($key)
    {
        return $this->get($key);
    }

    /**
     * Set collection item
     *
     * @param string $key   The data key
     * @param mixed  $value The data value
     */
    public function offsetSet($key, $value)
    {
        $this->set($key, $value);
    }

    /**
     * Remove item from collection
     *
     * @param string $key The data key
     */
    public function offsetUnset($key)
    {
        $this->remove($key);
    }

    /**
     * Get number of items in collection
     *
     * @return int
     */
    public function count()
    {
        return \count($this->data);
    }

    /**
     * Get collection iterator
     *
     * @return ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->data);
    }
}
