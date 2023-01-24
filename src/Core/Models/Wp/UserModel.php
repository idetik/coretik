<?php

namespace Coretik\Core\Models\Wp;

use Coretik\Core\Models\Traits\AcfFields;
use Coretik\Core\Models\Adapters\WPUserAdapter;

class UserModel extends WPModel
{
    use AcfFields;

    public function __construct($initializer = null)
    {
        $this->adapter = new WPUserAdapter($this);
        $this->dictionnary = new UserDictionnary();
        $this->name = 'users';

        switch (true) {
            case $initializer instanceof \WP_User:
                $this->id = $initializer->ID;
                $this->wp_object = $initializer;
                break;
            case \is_int($initializer):
                $this->id = $initializer;
                $this->wp_object = $this->adapter->get($initializer);
                break;
            default:
                break;
        }
        parent::__construct();

        $this->on('created', [$this, 'saveMeta']);
        $this->on('updated', [$this, 'saveMeta']);
    }

    public function saveMeta()
    {
        foreach ($this->metaKeys() as $key) {
            if (\property_exists($this, $key)) {
                if (!$this->isProtectedMeta($key)) {
                    $this->adapter->updateMeta($this->resolveMetaKey($key), $this->castMeta($key, $this->$key));
                }
            }
        }
    }

    public function get(string $prop)
    {
        if ($this->hasMeta($prop)) {
            return $this->meta($prop);
        }
        return parent::get($prop);
    }
}
