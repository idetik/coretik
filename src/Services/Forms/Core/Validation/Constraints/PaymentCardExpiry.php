<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

use Coretik\Services\Forms\Core\Utils;

class PaymentCardExpiry extends Constraint
{
    protected string $name = 'payment-card-expiry';
    protected string $message = 'This card expiry date has already passed';
    protected bool $display_message = true;
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
