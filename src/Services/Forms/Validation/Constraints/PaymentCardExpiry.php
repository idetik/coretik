<?php

namespace Coretik\Services\Forms\Validation\Constraints;

use Coretik\Services\Forms\Utils;

class PaymentCardExpiry extends Constraint
{
    private $name    = 'payment-card-expiry';
    private $message = 'This card expiry date has already passed';
    private $display_message = true;
    private $field_month;
    private $field_year;

    public function __construct($args = [])
    {
        if (!empty($args['field-month'])) {
            $this->field_month = $args['field-month'];
        }
        if (!empty($args['field-year'])) {
            $this->field_year = $args['field-year'];
        }
    }

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

    public function validate($fieldname, $value, $values)
    {
        $field_month = $this->field_month ?? $fieldname;
        $field_year = $this->field_year ?? $fieldname;

        if ((!Utils::issetValue($values[$field_month])) || (!Utils::issetValue($values[$field_year]))) {
            return true;
        }

        $timezone  = new \DateTimeZone(get_option('timezone_string'));
        $yesterday = new \DateTime('yesterday', $timezone);
        $field = \DateTime::createFromFormat('mY', $values[$field_month] . $values[$field_year], $timezone);

        return $yesterday < $field;
    }
}
