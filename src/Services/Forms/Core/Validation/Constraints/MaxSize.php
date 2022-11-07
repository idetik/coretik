<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

use Coretik\Services\Forms\Core\Utils;

class MaxSize extends Constraint
{
    private $name    = 'max';
    private $message = 'Trop long.';
    private $display_message = true;
    private $max;

    public function __construct($max)
    {
        $this->max = $max;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function isMessageDisplayed()
    {
        return $this->display_message;
    }

    public function validate($fieldname, $value, $values)
    {
        if (!Utils::issetValue($value)) {
            return true;
        }
        return strlen($value) <= $this->max;
    }
}
