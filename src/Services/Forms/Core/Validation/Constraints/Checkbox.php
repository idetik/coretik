<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

use Coretik\Services\Forms\Core\Utils;

class Checkbox extends Constraint
{
    protected string $name    = 'checkbox';
    protected string $message = 'Ce choix n\'est pas valide.';

    public function validate($fieldname, $value, $values)
    {
        if (!Utils::issetValue($value)) {
            return true;
        }
        return 'on' == $value;
    }
}
