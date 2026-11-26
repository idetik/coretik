<?php

namespace Coretik\Tests\Unit\Forms\Fixtures;

use Coretik\Services\Forms\Core\Exception;
use Coretik\Services\Forms\Enums\RgpdDataCategory;
use Coretik\Services\Forms\JsonForm;

class NewsletterForm extends JsonForm
{
    const ID = 'newsletter';

    public int $runs = 0;
    public ?string $failWith = null;
    public ?string $redirect = null;

    public function getRules(): array
    {
        return [
            'email' => [
                'name' => 'Email',
                'constraints' => ['required' => true],
                'rgpd' => ['category' => RgpdDataCategory::CONTACT_DATA, 'private' => true],
            ],
            'topic' => ['name' => 'Topic'],
        ];
    }

    protected function isValidContext(): bool
    {
        return true;
    }

    protected function run(): void
    {
        $this->runs++;
        if (null !== $this->failWith) {
            throw new Exception($this->failWith);
        }
        if (null !== $this->redirect) {
            $this->redirectTo($this->redirect);
        }
    }
}
