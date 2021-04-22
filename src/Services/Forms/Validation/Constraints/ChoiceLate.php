<?php

namespace Coretik\Services\Forms\Validation\Constraints;

use Coretik\Services\Forms\Utils;

class ChoiceLate extends Constraint
{
    private $name    = 'choice-late';
    private $message = 'Ce choix n\'est pas valide.';
    private $display_message = false;
    private $provider;
    private $mapping;

    public function __construct($args)
    {
        $defaults = [
            'provider' => '_return_empty_array',
            'mapping'  => [],
        ];
        $args = wp_parse_args($args, $defaults);
        $this->provider = $args['provider'];
        $this->mapping  = $args['mapping'];
    }

    public function getName()
    {
        return $this->name;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function setMessage($message)
    {
        $this->message = $message;
    }

    public function setDisplayMessage($display_message)
    {
        $this->display_message = $display_message;
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
        $provider_args = [];
        foreach ($this->mapping as $provider_key => $form_key) {
            if (isset($values[$form_key])) {
                $provider_args[$provider_key] = $values[$form_key];
            }
        }
        $choices = call_user_func($this->provider, $provider_args);
        return \apply_filters('coretik/forms/constraint/choice_late', array_key_exists($value, $choices), $this, $fieldname, $value, $values);
    }
}
