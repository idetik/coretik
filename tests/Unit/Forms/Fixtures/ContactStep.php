<?php

namespace Coretik\Tests\Unit\Forms\Fixtures;

use Coretik\Services\Forms\Enums\RgpdDataCategory;
use Coretik\Services\Forms\Tunnel\FormStep;

class ContactStep extends FormStep
{
    const STEP_NAME = 'contact';

    public string $title = 'Contact';

    public function getRules(): array
    {
        return [
            'email' => [
                'name' => 'Email',
                'constraints' => ['required' => true],
                'rgpd' => ['category' => RgpdDataCategory::CONTACT_DATA, 'private' => true],
            ],
            'newsletter' => ['name' => 'Newsletter'],
        ];
    }

    public function notices(): array
    {
        return 'old@example.org' === $this->getValue('email') ? ['Already registered'] : [];
    }
}
