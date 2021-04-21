<?php

namespace Coretik\Services\Forms\Validation\Constraints;

use Coretik\Services\Forms\Utils;

class PaymentCardNumber extends Constraint
{

    private $name    = 'payment-card-number';
    private $message = 'Invalid card number';
    private $display_message = true;

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
        if (!Utils\isset_value($value)) {
            return true;
        }

        $card_number = \TAR\WP\Forms\Utils\formNormalizeTextWithoutSpaces($value);

        $validation = \Freelancehunt\Validators\CreditCard::validCreditCard($card_number);

        return true === $validation['valid'] && $card_number === $validation['number'];
    }
}
