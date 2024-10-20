<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

use Coretik\Services\Forms\Core\Utils;

class RequiredOnSubmit extends Constraint
{
    protected string $name = 'required-on-submit';
    protected string $message = 'Ce champs est requis';
    private $display_message = false;

    public function validate($fieldname, $value, $values)
    {
        if (Utils::isActionRefresh()) {
            return true;
        }

        return Utils::issetValue($value);
    }
}
