<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

use Coretik\Services\Forms\Core\Utils;

class Choice extends Constraint
{
    protected string $name    = 'choice';
    protected string $message = 'Ce choix n\'est pas valide.';
    private $choices;

    public function __construct($choices)
    {
        $this->choices = $choices;
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
            if (
                !\array_key_exists($val, $this->choices)
                && !\in_array($val, $this->choices)
                ) {
                return false;
            }
        }

        return true;
    }
}
