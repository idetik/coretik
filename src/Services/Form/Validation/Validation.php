<?php

namespace Coretik\Services\Forms\Validation;

use Coretik\Services\Forms\Validation\Constraints\Callback;

class Validation
{
    /**
     * Data to validate
     * @var array
     */
    protected $validationData = [];

    /**
     * Validation rules for the current form
     * @var array
     */
    protected $validators = [];

    /**
     * Array of validation errors
     * @var array
     */
    protected $errors = [];

    /**
     * Is valide
     * @var boolean
     */
    protected $isValid = false;

    public function setValidator($fieldName, Validator $validator)
    {
        $this->validators[$fieldName] = $validator;
        return $this;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function addError($fieldName, $constraint)
    {
        $this->errors[$fieldName] = $constraint;
    }

    public function forceFieldError(string $fieldName, string $message)
    {
        $this->errors[$fieldName] = [
            new Callback([
                'name' => $fieldName,
                'message' => $message,
                'callback' => function() {
                    return false;
                }
            ])
        ];
    }

    public function isValid()
    {
        return $this->isValid;
    }

    public function setData(array $data)
    {
        $this->validationData = $data;
        return $this;
    }

    public function getData()
    {
        return $this->validationData;
    }

    public function run(array $data = null)
    {
        if ($data !== null) {
            $this->setData($data);
        }

        if (empty($this->validationData)) {
            return false;
        }

        $this->isValid = true;

        foreach ($this->validators as $fieldName => $validator) {
            if (!isset($this->validationData[$fieldName])) {
                if ($validator->hasFileConstraint()) {
                    $this->validationData[$fieldName] = '_FILE';
                } else {
                    $this->validationData[$fieldName] = null;
                }
            }

            if (!$validator->validate($fieldName, $this->validationData[$fieldName], $this->validationData)) {
                $this->isValid = false;
                $this->errors[$fieldName] = $validator->getViolations();
            }
        }
        return $this->isValid;
    }
}
