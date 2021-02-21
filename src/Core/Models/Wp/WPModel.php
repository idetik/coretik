<?php

namespace Coretik\Core\Models\Wp;

use Coretik\Core\Models\Model;

class WPModel extends Model
{
    protected $wp_object;

    public function __construct($initializer = null)
    {
        if (!empty($initializer)) {
            if (empty($this->wp_object)) {
                throw new \Exception("Unable to resolve initializer.");
            }
        }
        parent::__construct($initializer);
    }

    public function get(string $prop)
    {
        return $this->wp_object->$prop;
    }
}
