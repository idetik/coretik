<?php

namespace Coretik\Services\Forms\Validation\Constraints;

use Coretik\Services\Forms\Utils;

class EmailCheck extends Constraint
{

    private $name    = 'email-check';
    private $message = 'Les adresses e-mail ne correspondent pas.';
    private $display_message = true;
    private $email_field;

    public function __construct($email_field)
    {
        $this->email_field = $email_field;
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
        if (!Utils\isset_value($value)) {
            return true;
        }

        if (empty($values[$this->email_field])) {
            return true;
        }

        $email = $values[$this->email_field];

        return $email === $value;
    }
}
