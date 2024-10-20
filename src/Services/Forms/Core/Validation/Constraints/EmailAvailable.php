<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

use Coretik\Services\Forms\Core\Utils;

class EmailAvailable extends Constraint
{
    protected string $name    = 'email-available';
    protected string $message = 'Cette adresse email existe déjà.';
    protected bool $display_message = true;

    public function validate($fieldname, $value, $values)
    {
        if (!Utils::issetValue($value)) {
            return true;
        }

        return !\email_exists($value);
    }
}
