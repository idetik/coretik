<?php

namespace Coretik\Services\Forms\Validation\Constraints;

use Coretik\Services\Forms\Utils;

class Checkbox extends Constraint
{

    private $name    = 'checkbox';
    private $message = 'Ce choix n\'est pas valide.';
    private $display_message = false;

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
        if (!Utils\isset_value($value)) {
            return true;
        }
        return 'on' == $value;
    }
}
