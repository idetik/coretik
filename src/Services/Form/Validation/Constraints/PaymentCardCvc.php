<?php

namespace Coretik\Services\Forms\Validation\Constraints;

use Coretik\Services\Forms\Utils;

class PaymentCardCvc extends Constraint
{

    private $name    = 'payment-card-cvc';
    private $message = 'Invalid card CVC';
    private $display_message = true;
    private $payment_card_number_field;

    public function __construct($payment_card_number_field)
    {
        $this->payment_card_number_field = $payment_card_number_field;
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
        if (!Utils\isset_value($value)) {
            return true;
        }

        $card_number = $values[$this->payment_card_number_field] ?? null;

        if(empty($card_number)) {
            return true;
        }

        $card_number = \TAR\WP\Forms\Utils\formNormalizeTextWithoutSpaces($card_number);

        $validation_card_number = \Freelancehunt\Validators\CreditCard::validCreditCard($card_number);

        if(true !== $validation_card_number['valid'] || $card_number !== $validation_card_number['number']) {
            return true;
        }

        $value = \TAR\WP\Forms\Utils\formNormalizeTextWithoutSpaces($value);

        return \Freelancehunt\Validators\CreditCard::validCvc($value, $validation_card_number['type']);
    }
}
