<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

use Coretik\Services\Forms\Core\Utils;

class EmailCheck extends Constraint
{
    protected string $name = 'email-check';
    protected string $message = 'Les adresses e-mail ne correspondent pas.';
    protected bool $display_message = true;
    private $email_field;

    public function __construct($email_field)
    {
        $this->email_field = $email_field;
    }

    public function validate($fieldname, $value, $values)
    {
        if (!Utils::issetValue($value)) {
            return true;
        }

        if (empty($values[$this->email_field])) {
            return true;
        }

        $email = $values[$this->email_field];

        return $email === $value;
    }
}
