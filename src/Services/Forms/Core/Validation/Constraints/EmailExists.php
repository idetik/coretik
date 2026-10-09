<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

use Coretik\Services\Forms\Core\Utils;

/**
 * Fails when no account uses this email: it tells visitors which emails have an account.
 * On a lost password form, prefer a generic message. Enable the forms rate limit (coretik/forms/rate_limit) to slow down enumeration.
 */
class EmailExists extends Constraint
{
    protected string $name    = 'email-exists';
    protected string $message = "Cette adresse email n'existe pas.";
    protected bool $display_message = true;

    public function validate($fieldname, $value, $values)
    {
        if (!Utils::issetValue($value)) {
            return true;
        }

        return false !== \email_exists($value);
    }
}
