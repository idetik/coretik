<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

use Coretik\Services\Forms\Core\Utils;

class Numeric extends Constraint
{
    protected string $name = 'numeric';
    protected string $message = 'La valeur doit être numérique.';
    protected bool $display_message = true;

    public function validate($fieldname, $value, $values)
    {
        if (!Utils::issetValue($value)) {
            return true;
        }
        return is_numeric($value);
    }
}
