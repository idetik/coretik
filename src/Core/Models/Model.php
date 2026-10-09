<?php

namespace Coretik\Core\Models;

use Coretik\Core\Models\Interfaces\{
    AdapterInterface as Adapter,
    DictionnaryInterface as Dictionnary,
    ModelInterface,
};
use Coretik\Core\Models\Traits\{
    Bootable,
    Initializable,
    Hooks
};

#[\AllowDynamicProperties]
abstract class Model implements ModelInterface
{
    use Bootable;
    use Initializable;
    use Hooks;

    protected $id;
    protected $name = '';
    protected $adapter;
    protected $dictionnary;
    protected $state;

    /**
     * Number of writes in progress, by object name
     */
    private static array $persisting = [];

    /**
     * Construct
     */
    public function __construct($initializer = null, $mediator = null)
    {
        if (!empty($initializer)) {
            if (empty($this->id()) || empty($this->name)) {
                throw new \Exception("Unable to resolve initializer.");
            }
            if (empty($this->adapter)) {
                throw new \Exception("Unable to load adapter.");
            }
        } elseif (empty($this->name) && !empty($mediator)) {
            $this->setName($mediator->getName());
        }

        if (empty($this->name)) {
            throw new \Exception("Model name is empty for " . static::class);
        }

        static::bootIfNotBooted();
        $this->initialize();
        $this->initializeModel();
    }

    protected function initializeModel(): void
    {
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

    public function create(): static
    {
        // Not allowed, already exists
        if (!empty($this->id())) {
            return $this;
        }

        $this->trigger('creating');
        $this->id = $this->persist(fn () => $this->adapter->create($this->changes()));
        $this->trigger('created');

        return $this;
    }

    protected function update(): self
    {
        // Not allowed, doesnt exists
        if (empty($this->id())) {
            return $this;
        }

        $this->trigger('updating');
        $this->persist(fn () => $this->adapter->update($this->changes()));
        $this->trigger('updated');

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
        $this->persist(fn () => $this->adapter->delete(true));
        $this->trigger('deleted');
    }

    /**
     * Run a write through the adapter. WP hooks fired meanwhile (save_post, post_updated…) belong to this write:
     * handlers can skip them with isPersisting(), the model triggers its own events.
     */
    protected function persist(callable $write)
    {
        $name = (string)$this->name();
        self::$persisting[$name] = (self::$persisting[$name] ?? 0) + 1;
        try {
            return $write();
        } finally {
            self::$persisting[$name]--;
        }
    }

    /**
     * Whether a model of this object name (post type, taxonomy…) is being written through its adapter.
     */
    public static function isPersisting(string $name): bool
    {
        return !empty(self::$persisting[$name]);
    }

    public function get(string $prop)
    {
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
}
