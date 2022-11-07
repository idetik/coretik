<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

use Coretik\Services\Forms\Core\Utils;

class RequiredIfAllSet extends Constraint
{
    private $name = 'required-if-all-set';
    private $message = 'Ce champs est requis';
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
        foreach ($this->conditionnals as $field_name) {
            if (!Utils::issetValue($field_name, $values)) {
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
