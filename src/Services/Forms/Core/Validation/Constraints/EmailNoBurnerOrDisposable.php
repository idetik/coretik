<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

use Coretik\Services\Forms\Core\Utils;

class EmailNoBurnerOrDisposable extends Constraint
{
    protected string $name    = 'email-no-burner-or-disposable';
    protected string $message = "Sorry, we don't allow disposable email addresses. Please try a different email account.";
    protected bool $display_message = true;

    protected static $burnersDomains = null;

    public static function getEmailDomain($email)
    {
        return mb_substr(mb_strrchr($email, '@'), 1);
    }

    /**
     * Resolved through Composer: ROOT_DIR is only defined by wp-cubi.
     */
    protected static function burnerEmailsFile(): string
    {
        $package = 'wesbos/burner-email-providers';
        if (\class_exists(\Composer\InstalledVersions::class) && \Composer\InstalledVersions::isInstalled($package)) {
            return \Composer\InstalledVersions::getInstallPath($package) . '/emails.txt';
        }
        return (\defined('ROOT_DIR') ? \constant('ROOT_DIR') : '') . '/vendor/' . $package . '/emails.txt';
    }

    public static function isBurnerEmailDomain($email)
    {
        if (is_null(self::$burnersDomains)) {
            $file = static::burnerEmailsFile();
            if (!file_exists($file) || !is_readable($file)) {
                self::$burnersDomains = [];
            } else {
                self::$burnersDomains = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            }
        }

        if (empty(self::$burnersDomains)) {
            return false;
        }

        $domain = self::getEmailDomain($email);
        $domain = mb_strtolower($domain);

        return in_array($domain, self::$burnersDomains);
    }

    public function validate($fieldname, $value, $values)
    {
        if (!Utils::issetValue($value)) {
            return true;
        }

        $value = mb_strtolower($value);

        return ! self::isBurnerEmailDomain($value);
    }
}
