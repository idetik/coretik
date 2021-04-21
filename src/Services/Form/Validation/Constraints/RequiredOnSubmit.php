<?php

namespace Coretik\Services\Forms\Validation\Constraints;

use Coretik\Services\Forms\Utils;

class RequiredOnSubmit extends Constraint
{

    private $name    = 'required-on-submit';
    private $message = 'Ce champs est requis';
    private $display_message = false;

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
        if (Utils\is_action_refresh()) {
            return true;
        }

        return Utils\isset_value($value);
    }
}
