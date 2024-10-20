<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

use Coretik\Services\Forms\Core\Utils;

class RequiredIfFieldsNotEquals extends Constraint
{
    protected string $name = 'required-if-fields-not-equals';
    protected string $message = 'Ce champs est requis.';
    protected bool $display_message = false;
    private $conditionnals;

    public function __construct($conditionnals)
    {
        $this->conditionnals = $conditionnals;
    }

    public function validate($fieldname, $value, $values)
    {
        $required = true;
        foreach ($this->conditionnals as $field_name => $field_values) {
            $field_value = isset($values[$field_name]) ? $values[$field_name] : null;
            if (in_array($field_value, $field_values)) {
                $required = false;
            }
        }
        if ($required) {
            return Utils::issetValue($value);
        } else {
            return true;
        }
    }
}
