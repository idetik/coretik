<?php

namespace Coretik\Services\Forms\Utils;

function json_return_success_and_exit($message)
{
    wp_send_json_success(['message' => $message]);
    exit;
}

function json_return_error_and_exit($message)
{
    wp_send_json_error(['message' => $message]);
    exit;
}

function sanitize_form_field($string, $strip_tags = true)
{
    if (is_array($string)) {
        foreach ($string as &$el) {
            $el = sanitize_form_field($el, $strip_tags);
        }
    } else {
        $string = strval($string);
        $string = trim($string);
        $string = stripslashes($string);
        $string = remove_invisible_characters($string);
        if ($strip_tags) {
            $string = wp_strip_all_tags($string);
        }
    }
    return $string;
}

function remove_invisible_characters($str, $url_encoded = true)
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

function isset_value($value, $in_array = false)
{
    if (false === $in_array) {
        return isset($value) && null !== $value && '' !== $value;
    } else {
        return is_array($in_array) && isset($in_array[$value]) && isset_value($in_array[$value]);
    }
    return false;
}

function force_array($items)
{
    if (!is_array($items)) {
        $items = [$items];
    }
    return $items;
}

function is_action_refresh()
{
    return isset($_POST['action']) && 'tar_form_refresh' == $_POST['action'];
}

function concat_field_birthday($form, $fieldname = 'date_naissance')
{
    $day    = $form->getValue($fieldname . '-day', false);
    $month  = $form->getValue($fieldname . '-month', false);
    $year   = $form->getValue($fieldname . '-year', false);
    if ($day && $month && $year) {
        $_POST[$form->getFormName()][$fieldname] = $day . '/' . $month . '/' . $year;
    }
}

function formRemoveCrlChars($value)
{
    return \preg_replace('/[\x{0000}-\x{0008}\x{0011}-\x{0012}]/u', '', $value);
}

function formRemoveSpaces($value)
{
    $value = \preg_replace('/[\x{2000}-\x{200B}\x{202F}\x{205F}\x{3000}\x{FEFF}\x{00A0}\x{1680}\x{180E}]/u', '', $value);
    $value = str_replace(' ', '', $value);
    return $value;
}

function formNormalizeSpaces($value)
{
    return \preg_replace('/[\x{2000}-\x{200B}\x{202F}\x{205F}\x{3000}\x{FEFF}\x{00A0}\x{1680}\x{180E}]/u', ' ', $value);
}

function formNormalizeText($value, $character_mask = " \t\n\r\0\x0B")
{
    $value = \sanitize_text_field($value);
    $value = formRemoveCrlChars($value);
    $value = formNormalizeSpaces($value);
    $value = \trim($value, $character_mask);
    return $value;
}

function formNormalizeTextWithoutSpaces($value)
{
    $value = formNormalizeText($value);
    $value = formRemoveSpaces($value);
    return $value;
}

function formForceMaxSizeText($value, $max = 255)
{
    return \mb_substr($value, 0, $max);
}

function formSanitizeText($value, $max = 255)
{
    $value = formNormalizeText($value);
    $value = formForceMaxSizeText($value, $max);
    return $value;
}

function slugify($string)
{
    $string = remove_accents($string);
    $string = remove_invisible_characters($string);
    $string = mb_strtolower($string);
    $string = sanitize_title_with_dashes($string, "save");
    $string = sanitize_key($string);
    $string = str_replace([" ", '_'], '-', $string);
    $string = str_replace([" ", '_'], '-', $string);
    return $string;
}
