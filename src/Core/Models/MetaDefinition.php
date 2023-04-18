<?php

namespace Coretik\Core\Models;

use Coretik\Core\Models\Traits\Hooks;

class MetaDefinition
{
    use Hooks;

    protected $key;
    protected $localName;
    protected $isProtected = false;
    protected $rules = [];
    protected $cast = null;
    protected $defaultValue = null;

    public function __construct(string $local_name, string $meta_key)
    {
        $this->key = $meta_key;
        $this->localName = $local_name;
    }

    public function protect()
    {
        $this->isProtected = true;
        $this->trigger('protect');
        return $this;
    }

    public function protectWith(callable $rule)
    {
        $this->protect();
        $this->rules[] = $rule;
        return $this;
    }

    public function castTo(string $cast)
    {
        $this->cast = $cast;
        return $this;
    }

    public function setDefaultValue($defaultValue)
    {
        $this->defaultValue = $defaultValue;
        $this->trigger('set_default_value');
        return $this;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function localName(): string
    {
        return $this->localName;
    }

    public function rules()
    {
        return $this->rules;
    }

    public function protected(): bool
    {
        return $this->isProtected;
    }

    public function protectedFor($model)
    {
        if (!$this->protected()) {
            return false;
        }

        if (count($this->rules) === 0) {
            return true;
        }

        foreach ($this->rules() as $rule) {
            if (\call_user_func($rule, $model)) {
                return true;
            }
        }

        return false;
    }

    public function cast()
    {
        return $this->cast;
    }

    public function defaultValue()
    {
        return $this->defaultValue;
    }

    public function __toString()
    {
        return $this->key;
    }

    /**
     * Method required for Hooks trait
     */
    protected function name(): string
    {
        return $this->key;
    }
}
