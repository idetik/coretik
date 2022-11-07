<?php

namespace Coretik\Services\Forms\Core\Validation;

use Coretik\Services\Forms\Core\Validation\Constraints\Callback;

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

    private $form;

    public function __construct($form)
    {
        $this->form = $form;
    }

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
                'callback' => function () {
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

        $fieldNames = [];

        foreach ($this->validators as $fieldName => $validator) {
            $fieldNames[] = $fieldName;

            if (!$validator->validate($fieldName, $this->validationData[$fieldName] ?? null, $this->validationData)) {
                //Validation KO
                $this->isValid = false;
                $this->errors[$fieldName] = $validator->getViolations();
            } else {
                // Validation OK
                $form_name = $this->form->getFormName();
                if ($validator->hasFileConstraint() && !empty($_FILES[$form_name]['tmp_name'][$fieldName])) {
                    $path = $_FILES[$form_name]['tmp_name'][$fieldName];
                    $data = file_get_contents($path);
                    $this->validationData[$fieldName] = [
                        'type' => 'file',
                        'mime' => mime_content_type($path),
                        'name' => sanitize_file_name($_FILES[$form_name]['name'][$fieldName]),
                        'content' => \base64_encode($data)
                    ];
                }
            }
        }

        // Remove other posted fields
        $fieldNames = array_unique($fieldNames);
        $this->validationData = \array_filter(
            $this->validationData,
            fn ($fieldName) => \in_array($fieldName, $fieldNames),
            ARRAY_FILTER_USE_KEY
        );

        return $this->isValid;
    }
}
