<?php

namespace Coretik\Tests\Integration\Fixtures\Forms;

use Coretik\Services\Forms\Tunnel\FormStep;

/**
 * Step of the signup tunnel: renders its own field (no template in the test theme).
 */
class SignupStep extends FormStep
{
    public string $title = 'Account';
    public string $instructions = 'Your <strong>email</strong>';

    public function getRules(): array
    {
        return ['email' => ['name' => 'Email', 'constraints' => ['required' => true, 'email' => true]]];
    }

    public function render(bool $return = false): ?string
    {
        $html = \sprintf(
            '<div data-field="email"><input type="email" name="%s" value="%s"></div>',
            \esc_attr($this->form->getFormName() . '[email]'),
            \esc_attr((string)$this->getValue('email'))
        );

        if ($return) {
            return $html;
        }
        echo $html;
        return null;
    }
}
