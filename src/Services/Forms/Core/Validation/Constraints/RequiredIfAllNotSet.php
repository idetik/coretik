<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

use Coretik\Services\Forms\Core\Utils;

class RequiredIfAllNotSet extends Constraint
{
    protected string $name = 'required-if-all-not-set';
    protected string $message = 'Ce champs est requis.';
    private $conditionnals;

    public function __construct($conditionnals)
    {
        $this->conditionnals = $conditionnals;
    }

    public function validate($fieldname, $value, $values)
    {
        $required = true;
        foreach ($this->conditionnals as $field_name) {
            if (Utils::issetValue($field_name, $values)) {
                $required = false;
                break;
            }
        }
        if ($required) {
            return Utils::issetValue($value);
        } else {
            return true;
        }
    }
}
