<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

use Coretik\Services\Forms\Core\Utils;

class MinSize extends Constraint
{
    protected string $name = 'min';
    protected string $message = 'Trop court.';
    protected bool $display_message = true;
    private $min;

    public function __construct($min)
    {
        $this->min = $min;
    }

    public function validate($fieldname, $value, $values)
    {
        if (!Utils::issetValue($value)) {
            return true;
        }
        return strlen($value) >= $this->min;
    }
}
