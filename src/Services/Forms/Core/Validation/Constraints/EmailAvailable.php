<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

use Coretik\Services\Forms\Core\Utils;

/**
 * Fails when an account uses this email: it tells visitors which emails have an account.
 * Expected on a registration form, but enable the forms rate limit (coretik/forms/rate_limit) to slow down enumeration.
 */
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
