<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

use Coretik\Services\Forms\Core\Utils;

class RequiredIfOneSet extends Constraint
{
    protected string $name = 'required-if-one-set';
    protected string $message = 'Ce champs est requis.';
    private $conditionnals;

    public function __construct($conditionnals)
    {
        $this->conditionnals = $conditionnals;
    }

    public function validate($fieldname, $value, $values)
    {
        $required = false;
        foreach ($this->conditionnals as $field_name) {
            if (Utils::issetValue($field_name, $values)) {
                $required = true;
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
