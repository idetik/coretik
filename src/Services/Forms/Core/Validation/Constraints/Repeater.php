<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

use Coretik\Services\Forms\Core\Utils;
use Coretik\Services\Forms\Core\Validation;
use Coretik\Services\Forms\Validator;

class Repeater extends Constraint
{
    private $name    = 'repeater';
    private $message = 'Ce champs contient des erreurs.';
    private $display_message = false;
    private $validation = null; //Form Validation object
    protected $form;

    // constraints
    private $constraints = [
        'min_items'          => ['activate' => false, 'message' => 'Pas assez de réponse.', 'args' => true, 'callback' => 'minItems'],
        'max_items'          => ['activate' => false, 'message' => 'Trop de réponses.', 'args' => true, 'callback' => 'maxItems'],
        'fields'             => ['activate' => false, 'message' => '', 'args' => true, 'callback' => 'fields']
    ];

    public function __construct($constraints, $form)
    {
        $this->form = $form;

        foreach ($constraints as $constraint_name => $args) {
            if (!$args) {
                continue;
            }

            if (!isset($this->constraints[$constraint_name])) {
                continue;
            }
            $this->constraints[$constraint_name]['activate'] = true;

            if (\is_array($args)) {
                if (isset($args['message'])) {
                    $this->constraints[$constraint_name]['message'] = $args['message'];
                }
                if (isset($args['args'])) {
                    $args = $args['args'];
                }
            }

            if ($this->constraints[$constraint_name]['args']) {
                $this->constraints[$constraint_name]['args'] = $args;
            }
        }
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
        if (!Utils::issetValue($value)) {
            return true;
        }

        if (!is_array($value)) {
            return false;
        }

        foreach ($this->constraints as $constraint => $params) {
            if (!$params['activate']) {
                continue;
            }

            $callback = $params['callback'];
            if (!$this->$callback($value, $params['args'], $fieldname)) {
                $this->message = $params['message'];
                return false;
            }
        }

        return true;
    }

    private function minItems(array $value, $min)
    {
        return count($value) >= intval($min);
    }

    private function maxItems(array $value, $max)
    {
        return count($value) <= intval($max);
    }

    private function fields(array $value, $constraints, $fieldname)
    {
        $result = $this->validateSubFields($value, $constraints);
        $is_valid = true;

        foreach ($result as $index => $row) {
            if (!$row['is_valid']) {
                $is_valid = false;
                foreach ($row['errors'] as $subfield_name => $constraints) {
                    $this->form->getValidation()->addError(
                        sprintf(
                            '%s[%d][%s]',
                            $fieldname,
                            $index,
                            $subfield_name
                        ),
                        $constraints
                    );
                }
            }
        }

        return $is_valid;
    }

    private function validateSubFields(array $subFields, $constraints)
    {
        $this->validation = new Validation();

        foreach ($subFields as $index => $row) {
            $this->validation->setData($row);

            foreach ($constraints as $field_key => $data) {
                $validator  = new Validator();

                foreach ($data['constraints'] as $key => $args) {
                    $constraint = Constraint::factory($key, $args, $this->form);
                    if (false !== $constraint) {
                        $data['constraints'][$key] = $constraint;
                        $data['constraints_args'][$key] = $args;
                    }
                    $validator->addConstraint($constraint);
                }
                $this->validation->setValidator($field_key, $validator);
            }

            $result[$index] = [
                'is_valid' => $this->validation->run(),
                'data'     => $this->validation->getData(),
                'errors'   => $this->validation->getErrors()
            ];
        }

        return $result;
    }
}
