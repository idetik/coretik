<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

class Callback extends Constraint
{
    private $name;
    private $message;
    private $display_message = true;

    public function __construct($args)
    {
        $defaults = [
            'message'  => __('Ce champs contient une erreur.'),
            'args'     => [],
        ];
        $args = wp_parse_args($args, $defaults);
        $this->name     = $args['name'];
        $this->message  = $args['message'];
        $this->callback = $args['callback'];
        $this->args     = $args['args'];
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

    public function isMessageDisplayed()
    {
        return $this->display_message;
    }

    public function validate($fieldname, $value, $values)
    {
        return call_user_func($this->callback, $this, $this->args, $value, $values);
    }
}
