<?php

namespace Coretik\Core\Models;

use Coretik\Core\Models\Interfaces\AdapterInterface as Adapter;
use Coretik\Core\Models\Interfaces\ModelInterface;
use Coretik\Core\Models\Interfaces\DictionnaryInterface as Dictionnary;

#[\AllowDynamicProperties]
abstract class Model implements ModelInterface
{
    use Traits\Bootable;
    use Traits\Initializable;
    use Traits\Hooks;

    protected $id;
    protected $name = '';
    protected $adapter;
    protected $dictionnary;
    protected $state;

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
        $this->initialize();
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

    public function name(): ?string
    {
        return $this->name;
    }

    public function setName(string $value): self
    {
        $this->name = $value;
        return $this;
    }

    /**
     * CRUD
     */
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

    public function get(string $prop) {}

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
}
