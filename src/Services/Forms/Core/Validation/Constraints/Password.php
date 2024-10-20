<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

use Coretik\Services\Forms\Core\Utils;

class Password extends Constraint
{
    protected string $name = 'password';
    protected string $message = 'Le mot de passe est invalide.';
    protected bool $display_message = true;

    // constraints
    private $constraints = [
        'min_char'          => ['activate' => false, 'message' => 'Le mot de passe est trop court.', 'args' => true, 'callback' => 'minChar'],
        'max_char'          => ['activate' => false, 'message' => 'Le mot de passe est trop long.', 'args' => true, 'callback' => 'maxChar'],
        'least_one_number'  => ['activate' => false, 'message' => 'Le mot de passe doit contenir au moins un chiffre et une lettre.', 'args' => false, 'callback' => 'leastOneNumber'],
        'least_one_letter'  => ['activate' => false, 'message' => 'Le mot de passe doit contenir au moins une lettre.', 'args' => false, 'callback' => 'leastOneLetter'],
        'least_one_cap'     => ['activate' => false, 'message' => 'Le mot de passe doit contenir au moins une majuscule.', 'args' => false, 'callback' => 'leastOneCap'],
        'confirm_password'  => ['activate' => false, 'message' => 'Le mot de passe est invalide.', 'args' => false, 'callback' => 'confirmPassword'],
    ];

    public function __construct($password_constraints = [])
    {
        foreach ($password_constraints as $constraint_name => $args) {
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

    public function validate($fieldname, $value, $values)
    {
        if (!Utils::issetValue($value)) {
            return true;
        }

        foreach ($this->constraints as $constraint => $params) {
            if (!$params['activate']) {
                continue;
            }

            $callback = $params['callback'];
            if (!$this->$callback($value, $params['args'])) {
                $this->message = $params['message'];
                return false;
            }
        }

        return true;
    }

    private function minChar($value, $min)
    {
        return strlen($value) >= intval($min);
    }

    private function maxChar($value, $max)
    {
        return strlen($value) <= intval($max);
    }

    private function leastOneNumber($value)
    {
        return preg_match("#[0-9]+#", $value);
    }

    private function leastOneLetter($value)
    {
        return preg_match("#[a-z]+#", $value) || preg_match("#[A-Z]+#", $value);
    }

    private function leastOneCap($value)
    {
        return preg_match("#[A-Z]+#", $value);
    }

    private function confirmPassword($value)
    {
        $user = \wp_get_current_user();

        if (!$user->ID) {
            return false;
        }

        if (!\wp_check_password($value, $user->data->user_pass, $user->ID)) {
            return false;
        }

        return true;
    }
}
