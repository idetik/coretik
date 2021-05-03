<?php

namespace Coretik\Core\Models;

use Coretik\Core\Models\Interfaces\AdapterInterface as Adapter;
use Coretik\Core\Models\Interfaces\ModelInterface;
use Coretik\Core\Models\Interfaces\DictionnaryInterface as Dictionnary;
use Coretik\Core\Collection;

// todo jsonable ?

abstract class Model implements ModelInterface
{
    use Traits\Bootable;
    use Traits\Hooks;

    protected $id;
    protected $name = '';
    protected $adapter;
    protected $dictionnary;
    protected $state;

    // @todo observers
    // @todo States

    // abstract public function newBaseQueryBuilder();

    /**
     * Construct
     */
    public function __construct($initializer = null)
    {
        if (!empty($initializer)) {
            if (empty($this->id()) || empty($this->name)) {
                throw new \Exception("Unable to resolve initializer.");
            }
            if (empty($this->adapter)) {
                throw new \Exception("Unable to load adapter.");
            }
        }
        static::bootIfNotBooted();
        foreach (\Coretik\Core\Utils\Classes::classUsesDeep($this) as $traitNamespace) {
            $ref = new \ReflectionClass($traitNamespace);
            $traitName = $ref->getShortName();
            $initializer = 'initialize' . $traitName;
            if (method_exists($this, $initializer)) {
                $this->$initializer();
            }
        }
    }

    public function setDictionnary(Dictionnary $dictionnary)
    {
        $this->dictionnary = $dictionnary;
        return $this;
    }

    public function id(): int
    {
        return (int)$this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * CRUD
     */
    // @todo protected dictionnary fields ?
    public function changes(): array
    {
        $args = [];
        foreach ($this->dictionnary->all() as $key) {
            if (\property_exists($this, $key)) {
                $args[$key] = $this->$key;
            }
        }
        return $args;
    }

    public function create(): self
    {
        // Not allowed, already exists
        if (!empty($this->id())) {
            return null;
        }

        $this->trigger('creating');

        try {
            $this->id = $this->adapter->create($this->changes());
            $this->trigger('created');
        } catch (\RuntimeException $e) {
            throw $e;
        }

        return $this;
    }

    protected function update(): self
    {
         // Not allowed, doesnt exists
        if (empty($this->id())) {
            return null;
        }

        $this->trigger('updating');
        try {
            $this->adapter->update($this->changes());
            $this->trigger('updated');
        } catch (\RuntimeException $e) {
            throw $e;
        }
        return $this;
    }

    public function save(): self
    {
        $this->trigger('saving');
        if (!empty($this->id())) {
            $this->update();
        } else {
            $this->create();
        }
        $this->trigger('saved');
        return $this;
    }

    public function delete(): void
    {
        $this->trigger('deleting');
        try {
            $this->adapter->delete(true);
            $this->trigger('deleted');
        } catch (\RuntimeException $e) {
            throw $e;
        }
    }

    /**
     * Queries
     */
    // public function newQueryBuilder($query)
    // {
    //     return new Builder($query);
    // }

    // public function newQuery()
    // {
    //     return $this->newQueryBuilder(
    //         $this->newBaseQueryBuilder()
    //     )->setModel($this);
    // }



    // protected static function baseQuery($args = [])
    // {
    //     return new Query\Base($args);
    // }
    // public static function query($args = [])
    // {
    //     $args = wp_parse_args($args, [
    //         'posts_per_page' => get_option('posts_per_page'),
    //         'post_type'      => static::POST_TYPE,
    //     ]);

    //     return static::baseQuery($args)->setCollector([static::class, 'collect']);
    // }

    public function get(string $prop)
    {
        // return $this->wp_object->$prop;
    }

    public function __get($prop)
    {
        $method = 'get' . str_replace('_', '', ucwords($prop, '_')) . 'Attribute';
        if (method_exists($this, $method)) {
            return $this->$method();
        } else {
            return $this->get($prop);
        }
    }

    public function __set($prop, $value)
    {
        $method = 'set' . str_replace('_', '', ucwords($prop, '_')) . 'Attribute';
        if (method_exists($this, $method)) {
            $this->$method($value);
        } else {
            $this->$prop = $value;
        }
    }

    /**
     * Relationhips Ideas
     */
    // // Post to post
    // public function hasOne()
    // {
    //     // has child
    // }
    // public function hasMany()
    // {
    //     // has childs
    // }
    // public function belongsTo()
    // {
    //     // has parent
    // }
    // public function belongsToMany()
    // {
    //     // use taxonomy term pivot ?
    // }

    // // Metable Model to Metable model
    // public function hasOne($model_class)
    // {
    //     // build meta with key <thisName>_thisId_related_<modelName>_modelId
    // }
    // public function hasMany()
    // {
    //     // has childs
    // }
    // public function belongsTo()
    // {
    //     // has parent
    // }
    // public function belongsToMany()
    // {
    //     // has parents
    // }
}
