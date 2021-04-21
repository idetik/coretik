<?php

namespace Coretik\Services\Forms\Validation\Constraints;

use Coretik\Services\Forms\Utils;

class DateAfterToday extends Constraint
{

    private $name            = 'date-after-today';
    private $message         = "La date doit être supérieure ou égale à la date d'aujourd'hui.";
    private $display_message = true;
    private $format          = 'd/m/Y';

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
        $today = new \Datetime('now', get_option('timezone_string'));
        $today->setTime(0, 0, 0, 0);

        $date  = \DateTime::createFromFormat($this->format, $value, get_option('timezone_string'));
        $date->setTime(0, 0, 0, 0);

        return $date && ($date >= $today);
    }
}
