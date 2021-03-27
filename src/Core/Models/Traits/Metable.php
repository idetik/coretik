<?php

namespace Coretik\Core\Models\Traits;

use Coretik\Core\Collection;
use Coretik\Core\Models\Exceptions\AdapterNotFoundException;
use Coretik\Core\Models\Interfaces\MetableAdapterInterface;
use Coretik\Core\Models\MetaDefinition;
use Coretik\Core\Models\Exceptions\UndefinedMetaKeyException;
use Carbon\Carbon;

/**
 * All meta have to be declared
 */
trait Metable
{
    protected $metas;

    protected function initializeMetable()
    {
        $this->metas = new Collection();

        // @todo
        // this->on('saving') => protected meta // ou observers
        // get_post_meta => hook default value

        // if (\property_exists($this, 'metas')) {
        //     $this->declareMetas($this->metas);
        // }

        if (!$this->adapter instanceof MetableAdapterInterface) {
            throw new AdapterNotFoundException('Adapter of "' . __CLASS__ . '" does not implement MetableAdapterInterface.');
        }
    }

    public function addMeta(MetaDefinition $meta)
    {
        $this->metas->set($meta->localName(), $meta);
    }

    protected function declareMeta(string $local_key, string $meta_key = null, $protected = null): MetaDefinition
    {
        if ($this->hasMeta($local_key)) {
            return $this->metaDefinition($local_key);
        }

        if (empty($meta_key)) {
            $meta_key = $local_key;
        }

        $meta = new MetaDefinition($local_key, $meta_key);

        if (!empty($protected)) {
            if (\is_callable($protected)) {
                $meta->protectWith($protected);
            } elseif (true === $protected) {
                $meta->protect();
            }
        }

        $this->addMeta($meta);
        return $meta;
    }

    protected function declareMetas(array $metas)
    {
        foreach ($metas as $local_key => $meta_key) {
            if (\is_int($local_key)) {
                $local_key = $meta_key;
            }
            $this->declareMeta($local_key, $meta_key);
        }
    }

    /**
     * @param mixed $local_key : string or array of string
     */
    public function metaDefinition($key)
    {
        return $this->metas->get($this->resolveLocalKey($key));
    }

    public function allMetas()
    {
        return $this->metas->all();
    }

    /**
     * @param string $local_key Check if local key exists
     */
    public function hasMeta(string $key)
    {
        try {
            $this->resolveKey($key);
            return true;
        } catch (UndefinedMetaKeyException $e) {
            return false;
        }
    }

    public function isProtectedMeta(string $key): bool
    {
        if (!$this->hasMeta($key)) {
            return false;
        }
        return $this->metaDefinition($key)->protectedFor($this);
    }

    public function metaKeys(bool $local = true): array
    {
        return $local
                ? $this->metas->keys()
                : $this->metas->map(function ($item) {
                    return $item->key();
                })->all();
    }

    public function protectedMetaKeys(bool $local = true): array
    {
        $protected = $this->metas->filter(function ($item) {
            return $item->protected();
        });
        return $local
                ? $protected->keys()
                : $protected->map(function ($item) {
                    return $item->key();
                })->all();
    }

    /**
     * Return generator of all meta values
     */
    public function metaValues()
    {
        foreach ($this->metaKeys() as $key) {
            yield $key => $this->meta($key);
        }
    }

    /**
     * Get meta value from BDD
     */
    public function meta(string $key, $default = null)
    {
        if (empty($this->id)) {
            return $this->metaDefaultValue($key, $default);
        }

        try {
            $value = $this->adapter->meta($this->resolveMetaKey($key), $this->metaDefaultValue($key, $default));
            return $this->castMeta($key, $value);
        } catch (UndefinedMetaKeyException $e) {
            return $this->metaDefaultValue($key, $default);
        }
    }

    protected function metaDefaultValue(string $key, $default = null)
    {
        return $default ?? $this->metaDefinition($key)->defaultValue() ?? null;
    }

    protected function castMeta(string $key, $value)
    {
        if (is_null($value)) {
            return $value;
        }

        $cast_type = $this->metaDefinition($key)->cast();

        switch ($cast_type) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'real':
            case 'float':
            case 'double':
                return (float) $value;
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'object':
                return \json_decode($value, false);
            case 'array':
            // case 'json':
                return (array) $value;
            case 'collection':
                return new Collection((array) $value);
            case 'date':
                return $this->asDate($value);
            case 'datetime':
            case 'custom_datetime':
                return $this->asDateTime($value);
            case 'timestamp':
                return $this->asTimestamp($value);
            default:
                return $value;
        }
    }

    /**
     * Key resolvers
     */
    public function getMetaKeyFromLocalKey(string $local_key): string
    {
        return $this->metas->get($local_key)->key();
    }

    public function getLocalKeyFromMetaKey(string $meta_key): string
    {
        return $this->metas->keyOf(function ($meta) use ($meta_key) {
            return $meta->key() === $meta_key;
        });
    }

    protected function resolveLocalKey(string $key)
    {
        $keys = $this->resolveKey($key);
        return !empty($keys) ? $keys['local_key'] : null;
    }

    protected function resolveMetaKey(string $key)
    {
        $keys = $this->resolveKey($key);
        return !empty($keys) ? $keys['meta_key'] : null;
    }

    protected function resolveKey(string $key): array
    {
        if ($this->metas->has($key)) {
            return ['local_key' => $key, 'meta_key' => $this->getMetaKeyFromLocalKey($key)];
        }

        if (\in_array($key, $this->metaKeys(false))) {
            return ['local_key' => $this->getLocalKeyFromMetaKey($key), 'meta_key' => $key];
        }

        throw new UndefinedMetaKeyException("Unable to resolve meta key {$key}");
    }

    /**
     * Cast
     */
    /**
     * Return a timestamp as DateTime object with time set to 00:00:00.
     *
     * @param  mixed  $value
     * @return \Caron\Carbon
     */
    protected function asDate($value)
    {
        return $this->asDateTime($value)->startOfDay();
    }

    /**
     * Return a timestamp as DateTime object.
     *
     * @param  mixed  $value
     * @return \Carbon\Carbon
     */
    protected function asDateTime($value)
    {
        // If this value is already a Carbon instance, we shall just return it as is.
        // This prevents us having to re-instantiate a Carbon instance when we know
        // it already is one, which wouldn't be fulfilled by the DateTime check.
        if ($value instanceof Carbon) {
            return Carbon::instance($value);
        }

        // If the value is already a DateTime instance, we will just skip the rest of
        // these checks since they will be a waste of time, and hinder performance
        // when checking the field. We will just return the DateTime right away.
        if ($value instanceof DateTimeInterface) {
            return Carbon::parse(
                $value->format('Y-m-d H:i:s.u'),
                $value->getTimezone()
            );
        }

        // If this value is an integer, we will assume it is a UNIX timestamp's value
        // and format a Carbon object from this timestamp. This allows flexibility
        // when defining your date fields as they might be UNIX timestamps here.
        if (is_numeric($value)) {
            return Carbon::createFromTimestamp($value);
        }

        // If the value is in simply year, month, day format, we will instantiate the
        // Carbon instances from that format. Again, this provides for simple date
        // fields on the database, while still supporting Carbonized conversion.
        if ($this->isStandardDateFormat($value)) {
            return Carbon::instance(Carbon::createFromFormat('Y-m-d', $value)->startOfDay());
        }

        $format = $this->getDateFormat();

        // https://bugs.php.net/bug.php?id=75577
        if (version_compare(PHP_VERSION, '7.3.0-dev', '<')) {
            $format = str_replace('.v', '.u', $format);
        }

        // Finally, we will just assume this date is in the format used by default on
        // the database connection and use that format to create the Carbon object
        // that is returned back out to the developers after we convert it here.
        return Carbon::createFromFormat($format, $value);
    }

    /**
     * Determine if the given value is a standard date format.
     *
     * @param  string  $value
     * @return bool
     */
    protected function isStandardDateFormat($value)
    {
        return \preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value);
    }

    /**
     * Convert a DateTime to a storable string.
     *
     * @param  mixed  $value
     * @return string|null
     */
    public function fromDateTime($value)
    {
        return empty($value) ? $value : $this->asDateTime($value)->format(
            $this->getDateFormat()
        );
    }

    /**
     * Return a timestamp as unix timestamp.
     *
     * @param  mixed  $value
     * @return int
     */
    protected function asTimestamp($value)
    {
        return $this->asDateTime($value)->getTimestamp();
    }

    protected function getDateFormat()
    {
        return 'Y-m-d H:i:s.u';
    }
}
