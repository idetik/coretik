<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

use Coretik\Services\Forms\Core\Utils;

class Date extends Constraint
{
    private $name    = 'date';
    private $message;
    private $display_message = true;
    private $format;

    public function __construct($args)
    {
        $defaults = [
            'message'  => 'La date est invalide.',
            'format'   => 'd/m/Y',
        ];
        $args = wp_parse_args($args, $defaults);
        $this->message  = $args['message'];
        $this->format = $args['format'];
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
        if (!Utils::issetValue($value)) {
            return true;
        }
        $date = \DateTime::createFromFormat($this->format, $value);
        return $date && $date->format($this->format) == $value;
    }
}
