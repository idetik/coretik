<?php

namespace Coretik\Core;

use ArrayIterator;
use Traversable;
use Coretik\Core\Interfaces\CollectionInterface;
use Illuminate\Support\Collection as IlluminateCollection;

class Collection extends IlluminateCollection implements CollectionInterface
{
    /**
     * The source data
     *
     * @var array
     */
    protected $data = [];

    /**
     * {@inheritdoc}
     */
    public function set($key, $value)
    {
        $this->offsetSet($key, $value);
        return $this;
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

    // /**
    //  * {@inheritdoc}
    //  */
    // public function groupBy(callable $group_by, bool $hide_empty = false)
    // {
    //     $groups = [];
    //     foreach ($this->data as $object) {
    //         $group_key = \call_user_func($group_by, $object, $groups);
    //         if (!empty($group_key) && $group_key) {
    //             $groups[$group_key][] = $object;
    //         } elseif (!$hide_empty) {
    //             $groups['_others'][] = $object;
    //         }
    //     }
    //     ksort($groups);
    //     return new static($groups);
    // }

    /**
     * {@inheritdoc}
     */
    public function makeTree(string $parentKey, string $primaryKey): array
    {
        $data = $this->items;
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
        return $this->pull($key);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->items = [];
    }
}
