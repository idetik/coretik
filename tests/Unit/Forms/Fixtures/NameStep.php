<?php

namespace Coretik\Tests\Unit\Forms\Fixtures;

use Coretik\Services\Forms\Tunnel\FormStep;

class NameStep extends FormStep
{
    public string $title = 'Name';
    public int $successes = 0;

    public function getRules(): array
    {
        return [
            'name' => [
                'name' => 'Name',
                'constraints' => ['required' => true],
                'rgpd' => ['category' => 'identity'],
            ],
        ];
    }

    public function onSuccess(): void
    {
        $this->successes++;
    }
}
