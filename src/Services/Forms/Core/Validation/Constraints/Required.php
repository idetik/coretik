<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

use Coretik\Services\Forms\Core\Utils;

class Required extends Constraint
{
    protected string $name    = 'required';
    protected string $message = 'Ce champs est requis.';

    public function validate($fieldname, $value, $values)
    {
        return Utils::issetValue($value);
    }
}
