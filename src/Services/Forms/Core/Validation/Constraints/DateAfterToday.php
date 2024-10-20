<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

use Coretik\Services\Forms\Core\Utils;

class DateAfterToday extends Constraint
{
    protected string $name = 'date-after-today';
    protected bool $display_message = true;
    private $format;

    public function __construct($args)
    {
        $defaults = [
            'message'  => 'La date doit être supérieure ou égale à la date d\'aujourd\'hui.',
            'format'   => 'd/m/Y',
        ];
        $args = wp_parse_args($args, $defaults);
        $this->message  = $args['message'];
        $this->format = $args['format'];
    }

    public function validate($fieldname, $value, $values)
    {
        if (!Utils::issetValue($value)) {
            return true;
        }
        $today = new \Datetime('now', \wp_timezone());
        $today->setTime(0, 0, 0, 0);

        $date  = \DateTime::createFromFormat($this->format, $value, \wp_timezone());
        $date->setTime(0, 0, 0, 0);

        return $date && ($date >= $today);
    }
}
