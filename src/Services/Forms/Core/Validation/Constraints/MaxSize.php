<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

use Coretik\Services\Forms\Core\Utils;

class MaxSize extends Constraint
{
    protected string $name = 'max';
    protected string $message = 'Trop long.';
    protected bool $display_message = true;
    private $max;

    public function __construct($max)
    {
        $this->max = $max;
    }

    public function validate($fieldname, $value, $values)
    {
        if (!Utils::issetValue($value)) {
            return true;
        }
        // Characters, not bytes: "Zoé" is 3 long (UTF-8), as the maxlength attribute of the browser
        return \mb_strlen((string)$value) <= $this->max;
    }
}
