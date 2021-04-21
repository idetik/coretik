<?php

namespace Coretik\Services\Forms\Validation\Constraints;

use Coretik\Services\Forms\Utils;

class EmailExists extends Constraint
{

    private $name    = 'email-exists';
    private $message = "Cette adresse email n'existe pas.";
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
        if (!Utils\isset_value($value)) {
            return true;
        }

        return \email_exists($value);
    }
}
