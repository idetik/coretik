<?php

namespace Coretik\Services\Forms\Validation\Constraints;

use Coretik\Services\Forms\Utils;

class Integer extends Constraint
{
    private $name    = 'integer';
    private $message = 'La valeur doit être un nombre entier.';
    private $display_message = true;

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

        $value = Utils::formRemoveSpaces($value);
        return !!filter_var($value, FILTER_VALIDATE_INT);
    }
}
