<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

use Coretik\Services\Forms\Core\Utils;

class PaymentCardCvc extends Constraint
{
    protected string $name = 'payment-card-cvc';
    protected string $message = 'Invalid card CVC';
    protected bool $display_message = true;
    private $payment_card_number_field;

    public function __construct($payment_card_number_field)
    {
        $this->payment_card_number_field = $payment_card_number_field;
    }

    public function validate($fieldname, $value, $values)
    {
        if (!Utils::issetValue($value)) {
            return true;
        }

        $card_number = $values[$this->payment_card_number_field] ?? null;

        if (empty($card_number)) {
            return true;
        }

        $card_number = Utils::formNormalizeTextWithoutSpaces($card_number);

        $validation_card_number = \Freelancehunt\Validators\CreditCard::validCreditCard($card_number);

        if (true !== $validation_card_number['valid'] || $card_number !== $validation_card_number['number']) {
            return true;
        }

        $value = Utils::formNormalizeTextWithoutSpaces($value);

        return \Freelancehunt\Validators\CreditCard::validCvc($value, $validation_card_number['type']);
    }
}
