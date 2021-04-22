<?php

namespace Coretik\Services\Forms\Validation\Constraints;

use Coretik\Services\Forms\Utils;

class EqualsField extends Constraint
{

    private $name    = 'equals-field';
    private $message = 'Le champ doit être égal au champ %field_equal_label%.';
    private $display_message = true;
    private $field_equal;

    public function __construct($field_equal, $form)
    {
        $this->form = $form;
        $this->field_equal = $field_equal;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getMessage()
    {
        return str_replace('%field_equal_label%', $this->form->fieldLabel($this->field_equal), $this->message);
    }

    public function isMessageDisplayed()
    {
        return $this->display_message;
    }

    public function validate($fieldname, $value, $values)
    {
        if (!Utils::issetValue($value)) {
            return true;
        }

        return $value === $values[$this->field_equal];
    }
}
