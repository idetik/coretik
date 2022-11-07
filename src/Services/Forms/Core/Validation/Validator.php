<?php

namespace Coretik\Services\Forms\Core\Validation;

use Coretik\Services\Forms\Core\Validation\Constraints\Constraint;
use Coretik\Services\Forms\Core\Validation\Constraints\File as ConstraintFile;

class Validator
{
    protected $constraints = [];

    protected $violations = [];

    public function addConstraint(Constraint $constraint)
    {
        $this->constraints[] = $constraint;
    }

    public function getViolations()
    {
        return $this->violations;
    }

    public function validate($fieldname, $value, $values)
    {
        $this->violations = [];
        $isValid = true;
        foreach ($this->constraints as $constraint) {
            if (!$constraint->validate($fieldname, $value, $values)) {
                $this->violations[] = $constraint;
                $isValid = false;
                return $isValid;
            }
        }
        return $isValid;
    }

    public function hasFileConstraint()
    {
        foreach ($this->constraints as $constraint) {
            if ($constraint instanceof ConstraintFile) {
                return true;
            }
        }
        return false;
    }
}
