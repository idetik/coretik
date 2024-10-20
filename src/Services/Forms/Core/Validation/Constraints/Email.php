<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

use Coretik\Services\Forms\Core\Utils;

class Email extends Constraint
{
    protected string $name    = 'email';
    protected string $message = 'L\'adresse email est invalide.';
    protected bool $display_message = true;

    public static function getEmailDomain($email)
    {
        return mb_substr(mb_strrchr($email, '@'), 1);
    }

    public static function hasValidEmailDomain($email)
    {
        $domain = self::getEmailDomain($email);
        return checkdnsrr($domain . '.', "MX") || checkdnsrr($domain . '.', "A");
    }

    public function validate($fieldname, $value, $values)
    {
        if (!Utils::issetValue($value)) {
            return true;
        }

        $value = mb_strtolower($value);

        if (empty(filter_var($value, FILTER_VALIDATE_EMAIL))) {
            return false;
        }

        return self::hasValidEmailDomain($value);
    }
}
