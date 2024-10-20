<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

use Coretik\Services\Forms\Core\Utils;

class EqualsField extends Constraint
{
    protected string $name = 'equals-field';
    protected string $message = 'Le champ doit être égal au champ %field_equal_label%.';
    protected bool $display_message = true;
    private $field_equal;

    public function __construct($field_equal, $form)
    {
        $this->form = $form;
        $this->field_equal = $field_equal;
    }

    public function validate($fieldname, $value, $values)
    {
        if (!Utils::issetValue($value)) {
            return true;
        }

        return $value === $values[$this->field_equal];
    }
}
