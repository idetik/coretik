<?php

namespace Coretik\Services\Forms\Core\Validation\Constraints;

use Coretik\Services\Forms\Core\Utils;

class PaymentCardNumber extends Constraint
{
    protected string $name = 'payment-card-number';
    protected string $message = 'Invalid card number';
    protected bool $display_message = true;

    public function validate($fieldname, $value, $values)
    {
        if (!Utils::issetValue($value)) {
            return true;
        }

        $card_number = Utils::formNormalizeTextWithoutSpaces($value);

        $validation = \Freelancehunt\Validators\CreditCard::validCreditCard($card_number);

        return true === $validation['valid'] && $card_number === $validation['number'];
    }
}
