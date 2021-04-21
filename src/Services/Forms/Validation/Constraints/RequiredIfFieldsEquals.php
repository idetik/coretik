<?php

namespace Coretik\Services\Forms\Validation\Constraints;

use Coretik\Services\Forms\Utils;

class RequiredIfFieldsEquals extends Constraint
{

    private $name = 'required-if-fields-equals';
    private $message = 'Ce champs est requis.';
    private $display_message = false;
    private $conditionnals;

    public function __construct($conditionnals)
    {
        $this->conditionnals = $conditionnals;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function isMessageDisplayed()
    {
        return $this->display_message;
    }

    public function validate($fieldname, $value, $values)
    {
        $required = true;
        foreach ($this->conditionnals as $field_name => $field_values) {
            $field_value = isset($values[$field_name]) ? $values[$field_name] : null;
            if (!in_array($field_value, $field_values)) {
                $required = false;
            }
        }
        if ($required) {
            return Utils\isset_value($value);
        } else {
            return true;
        }
    }
}