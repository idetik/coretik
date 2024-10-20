<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

use Coretik\Services\Forms\Core\Utils;

class PasswordCheck extends Constraint
{
    protected string $name = 'password-check';
    protected string $message = 'Les mots de passe ne correspondent pas.';
    private $password_field;
    protected bool $display_message = true;

    public function __construct($password_field)
    {
        $this->password_field = $password_field;
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
