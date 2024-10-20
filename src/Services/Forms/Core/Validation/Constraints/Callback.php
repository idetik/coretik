<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

class Callback extends Constraint
{
    protected bool $display_message = true;
    private $callback;
    private $args;

    public function __construct($args)
    {
        $defaults = [
            'message'  =>'Ce champs contient une erreur.',
            'args'     => [],
        ];
        $args = wp_parse_args($args, $defaults);
        $this->name     = $args['name'];
        $this->message  = $args['message'];
        $this->callback = $args['callback'];
        $this->args     = $args['args'];
    }

    public function setMessage($message)
    {
        $this->message = $message;
    }

    public function validate($fieldname, $value, $values)
    {
        return call_user_func($this->callback, $this, $this->args, $value, $values);
    }
}
