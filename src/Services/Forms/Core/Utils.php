<?php

namespace Coretik\Services\Forms\Core;

class Utils
{
    public static function jsonReturnSuccessAndExit($message)
    {
        wp_send_json_success(['message' => $message]);
        exit;
    }

    public static function jsonReturnErrorAndExit($message)
    {
        wp_send_json_error(['message' => $message]);
        exit;
    }

    public static function sanitizeFormField($string, $strip_tags = true)
    {
        if (is_array($string)) {
            foreach ($string as &$el) {
                $el = static::sanitizeFormField($el, $strip_tags);
            }
        } else {
            $string = strval($string);
            $string = trim($string);
            $string = stripslashes($string);
            $string = static::removeInvisibleCharacters($string);
            if ($strip_tags) {
                $string = wp_strip_all_tags($string);
            }
        }
        return $string;
    }

    public static function removeInvisibleCharacters($str, $url_encoded = true)
    {
        $non_displayables = [];

        // every control character except newline (dec 10),
        // carriage return (dec 13) and horizontal tab (dec 09)
        if ($url_encoded) {
            $non_displayables[] = '/%0[0-8bcef]/i'; // url encoded 00-08, 11, 12, 14, 15
            $non_displayables[] = '/%1[0-9a-f]/i'; // url encoded 16-31
        }

        $non_displayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S'; // 00-08, 11, 12, 14-31, 127

        do {
            $str = preg_replace($non_displayables, '', $str, -1, $count);
        } while ($count);

        return $str;
    }

    public static function issetValue($value, $in_array = false)
    {
        if (false === $in_array) {
            return isset($value) && null !== $value && '' !== $value;
        } else {
            return is_array($in_array) && isset($in_array[$value]) && static::issetValue($in_array[$value]);
        }
        return false;
    }

    public static function forceArray($items)
    {
        if (!is_array($items)) {
            $items = [$items];
        }
        return $items;
    }

    public static function isActionRefresh()
    {
        return (isset($_POST['action']) && 'coretik_form_refresh' == $_POST['action'])
            || (isset($_POST['coretik_form_refresh']) && '1' === $_POST['coretik_form_refresh']);
    }

    public static function concatFieldBirthday($form, $fieldname = 'birthday')
    {
        $day    = $form->getValue($fieldname . '-day', false);
        $month  = $form->getValue($fieldname . '-month', false);
        $year   = $form->getValue($fieldname . '-year', false);
        if ($day && $month && $year) {
            $_POST[$form->getFormName()][$fieldname] = $day . '/' . $month . '/' . $year;
        }
    }

    public static function formRemoveCrlChars($value)
    {
        return \preg_replace('/[\x{0000}-\x{0008}\x{0011}-\x{0012}]/u', '', $value);
    }

    public static function formRemoveSpaces($value)
    {
        $value = \preg_replace('/[\x{2000}-\x{200B}\x{202F}\x{205F}\x{3000}\x{FEFF}\x{00A0}\x{1680}\x{180E}]/u', '', $value);
        $value = str_replace(' ', '', $value);
        return $value;
    }

    public static function formNormalizeSpaces($value)
    {
        return \preg_replace('/[\x{2000}-\x{200B}\x{202F}\x{205F}\x{3000}\x{FEFF}\x{00A0}\x{1680}\x{180E}]/u', ' ', $value);
    }

    public static function formNormalizeText($value, $character_mask = " \t\n\r\0\x0B")
    {
        $value = \sanitize_text_field($value);
        $value = static::formRemoveCrlChars($value);
        $value = static::formNormalizeSpaces($value);
        $value = \trim($value, $character_mask);
        return $value;
    }

    public static function formNormalizeTextWithoutSpaces($value)
    {
        $value = static::formNormalizeText($value);
        $value = static::formRemoveSpaces($value);
        return $value;
    }

    public static function formForceMaxSizeText($value, $max = 255)
    {
        return \mb_substr($value, 0, $max);
    }

    public static function formSanitizeText($value, $max = 255)
    {
        $value = static::formNormalizeText($value);
        $value = static::formForceMaxSizeText($value, $max);
        return $value;
    }

    public static function slugify($string)
    {
        $string = remove_accents($string);
        $string = static::removeInvisibleCharacters($string);
        $string = mb_strtolower($string);
        $string = sanitize_title_with_dashes($string, "save");
        $string = sanitize_key($string);
        $string = str_replace([" ", '_'], '-', $string);
        $string = str_replace([" ", '_'], '-', $string);
        return $string;
    }

    public static function hasBracket($string)
    {
        $bracket = strpos($string, '[');
        return false !== $bracket;
    }

    public static function removeBracket($string)
    {
        if (!static::hasBracket($string)) {
            return $string;
        }

        $bracket = strpos($string, '[');
        preg_match_all("/\[([^\]]+)\]/", $string, $matches);
        return substr($string, 0, $bracket);
    }
}
