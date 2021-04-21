<?php

namespace Coretik\Services\Forms\Validation\Constraints;

use Coretik\Services\Forms\Utils;

class Email extends Constraint
{

    private $name    = 'email';
    private $message = 'L\'adresse email est invalide.';
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
        if (!Utils\isset_value($value)) {
            return true;
        }

        $value = mb_strtolower($value);

        if(empty(filter_var($value, FILTER_VALIDATE_EMAIL))) {
            return false;
        }

        return self::hasValidEmailDomain($value);
    }
}
