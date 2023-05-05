<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

use Coretik\Services\Forms\Core\Utils;

class Choice extends Constraint
{
    private $name    = 'choice';
    private $message = 'Ce choix n\'est pas valide.';
    private $display_message = false;
    private $choices;

    public function __construct($choices)
    {
        $this->choices = $choices;
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

    public function getChoices()
    {
        return $this->choices;
    }

    public function validate($fieldname, $value, $values)
    {
        if (!Utils::issetValue($value)) {
            return true;
        }

        $value = Utils::forceArray($value);

        foreach ($value as $val) {
            if (!\array_key_exists($val, $this->choices)) {
                return false;
            }
        }

        return true;
    }
}
