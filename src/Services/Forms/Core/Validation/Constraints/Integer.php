<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

use Coretik\Services\Forms\Core\Utils;

class Integer extends Constraint
{
    protected string $name = 'integer';
    protected string $message = 'La valeur doit être un nombre entier.';
    protected bool $display_message = true;

    public function validate($fieldname, $value, $values)
    {
        if (!Utils::issetValue($value)) {
            return true;
        }

        $value = Utils::formRemoveSpaces($value);
        return !!filter_var($value, FILTER_VALIDATE_INT);
    }
}
