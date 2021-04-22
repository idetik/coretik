<?php

namespace Coretik\Services\Forms\Validation\Constraints;

use Coretik\Services\Forms\Utils;

class MinSize extends Constraint
{

    private $name    = 'min';
    private $message = 'Trop court.';
    private $display_message = true;
    private $min;

    public function __construct($min)
    {
        $this->min = $min;
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
        return strlen($value) >= $this->min;
    }
}
