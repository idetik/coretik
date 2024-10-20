<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

use Coretik\Services\Forms\Core\Utils;

class Phone extends Constraint
{
    protected string $name = 'phone';
    protected string $message = 'Le numéro de téléphone est invalide.';
    protected bool $display_message = true;

    public function validate($fieldname, $value, $values)
    {
        if (!Utils::issetValue($value)) {
            return true;
        }
        return strlen($value) >= 10;
    }
}
