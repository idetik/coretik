<?php

namespace Coretik\Services\Forms\Validation\Constraints;

use Coretik\Services\Forms\Utils;

class EmailNoBurnerOrDisposable extends Constraint
{

    private $name    = 'email-no-burner-or-disposable';
    private $message = "Sorry, we don't allow disposable email addresses. Please try a different email account.";
    private $display_message = true;

    protected static $burnersDomains = null;

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

    public static function isBurnerEmailDomain($email)
    {
        if(is_null(self::$burnersDomains)) {
            $file = ROOT_DIR . '/vendor/wesbos/burner-email-providers/emails.txt';
            if(!file_exists($file) || !is_readable($file)) {
                self::$burnersDomains = [];
            } else {
                self::$burnersDomains = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            }
        }

        if(empty(self::$burnersDomains)) {
            return false;
        }

        $domain = self::getEmailDomain($email);
        $domain = mb_strtolower($domain);

        return in_array($domain, self::$burnersDomains);
    }

    public function validate($fieldname, $value, $values)
    {
        if (!Utils\isset_value($value)) {
            return true;
        }

        $value = mb_strtolower($value);

        return ! self::isBurnerEmailDomain($value);
    }
}
