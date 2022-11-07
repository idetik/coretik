<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

use Coretik\Services\Forms\Core\Utils;

class Date extends Constraint
{
    private $name    = 'date';
    private $message = 'La date est invalide.';
    private $display_message = true;
    private $format  = 'd/m/Y';

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
        $date = \DateTime::createFromFormat($this->format, $value);
        return $date && $date->format($this->format) == $value;
    }
}
