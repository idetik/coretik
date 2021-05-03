<?php

namespace Coretik\Core\Models\Wp;

use Coretik\Core\Models\Interfaces\CRUDInterface;

class Option implements CRUDInterface
{
    protected $key;
    protected $value;
    protected $autoload;

    public function set(string $key, $value, bool $autoload = true)
    {
        $this->key = $key;
        $this->value = $value;
        $this->autoload = $autoload;

        if ($this->exists()) {
            $this->update();
        } else {
            $this->create();
        }
    }

    public function get($default = false)
    {
        return \get_option($this->key, $default);
    }

    public function create()
    {
        \add_option($this->key, $this->value, '', $this->autoload);
    }

    public function update()
    {
        \update_option($this->key, $this->value, $this->autoload);
    }

    public function exists()
    {
        return !empty($this->get(null));
    }

    public function delete()
    {
        \delete_option($this->key);
    }

    public function __invoke($key, $default = false)
    {
        $this->key = $key;
        return $this->get($default);
    }
}
