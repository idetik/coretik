<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

abstract class Constraint
{
    protected $form = null;

    abstract public function getName();

    abstract public function getMessage();

    abstract public function isMessageDisplayed();

    abstract public function validate($fieldname, $value, $values);

    protected static function get($class, array $args = [])
    {
        $class = apply_filters('coretik/forms/constraint/get', __NAMESPACE__ . '\\' . $class, $args);
        $class = apply_filters('coretik/forms/constraint/get/' . $class, $class, $args);

        if (\class_exists($class, true)) {
            return new $class(...$args);
        }

        return false;
    }

    public static function factory($key, $args, $form = null)
    {
        switch ($key) {
            case 'callback':
                return static::get('Callback', [$args]);
            case 'required':
                if (true === $args) {
                    return static::get('Required');
                } else {
                    return false;
                }
                break;
            case 'email':
                if (true === $args) {
                    return static::get('Email');
                }
                break;
            case 'checkbox':
                if (true === $args) {
                    return static::get('Checkbox');
                }
                break;
            case 'phone':
                if (true === $args) {
                    return static::get('Phone');
                }
                break;
            case 'date':
                return static::get('Date', [$args]);
            case 'date-after-today':
                return static::get('DateAfterToday', [$args]);
            case 'date-before-today':
                return static::get('DateBeforeToday', [$args]);
            case 'numeric':
                if (true === $args) {
                    return static::get('Numeric');
                }
                break;
            case 'integer':
                if (true === $args) {
                    return static::get('Integer');
                }
                break;
            case 'min-size':
                return static::get('MinSize', [$args]);
            case 'max-size':
                return static::get('MaxSize', [$args]);
            case 'file':
                return static::get('File', [$args]);
            case 'choice':
                return static::get('Choice', [$args]);
            case 'choice-late':
                return static::get('ChoiceLate', [$args]);
            case 'required-if-all-not-set':
                return static::get('RequiredIfAllNotSet', [$args]);
            case 'required-if-all-set':
                return static::get('RequiredIfAllSet', [$args]);
            case 'required-if-one-not-set':
                return static::get('RequiredIfOneNotSet', [$args]);
            case 'required-if-one-set':
                return static::get('RequiredIfOneSet', [$args]);
            case 'required-if-fields-equals':
                return static::get('RequiredIfFieldsEquals', [$args]);
            case 'required-if-fields-not-equals':
                return static::get('RequiredIfFieldsNotEquals', [$args]);
            case 'email-check':
                return static::get('EmailCheck', [$args]);
            case 'email-available':
                return static::get('EmailAvailable', [$args]);
            case 'email-exists':
                return static::get('EmailExists', [$args]);
            case 'email-no-burner-or-disposable':
                if (true === $args) {
                    return static::get('EmailNoBurnerOrDisposable');
                }
                break;
            case 'equals-field':
                return static::get('EqualsField', [$args, $form]);
            case 'password':
                return static::get('Password', [$args]);
            case 'password-check':
                return static::get('PasswordCheck', [$args]);
            case 'required-on-submit':
                return static::get('RequiredOnSubmit', [$args]);
            case 'user-login':
                return static::get('UserLogin', [$args]);
            case 'user-password':
                return static::get('UserPassword', [$args]);
            case 'payment-card-number':
                return static::get('PaymentCardNumber');
            case 'payment-card-cvc':
                return static::get('PaymentCardCvc', [$args]);
            case 'payment-card-expiry':
                return static::get('PaymentCardExpiry', [$args]);
            case 'repeater':
                return static::get('Repeater', [$args, $form]);
            default:
                return static::get($key, [$args, $form]);
        }
    }
}
