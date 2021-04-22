<?php

namespace Coretik\Services\Forms\Validation\Constraints;

use Coretik\Services\Forms\Utils;

class PasswordCheck extends Constraint
{

    private $name    = 'password-check';
    private $message = 'Les mots de passe ne correspondent pas.';
    private $password_field;
    private $display_message = true;
    
    public function __construct($password_field)
    {
        $this->password_field = $password_field;
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

        if (empty($values[$this->password_field])) {
            return true;
        }
        
        $password = $values[$this->password_field];
        
        return $password === $value;
    }
}
