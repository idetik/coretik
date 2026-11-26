<?php

namespace Coretik\Tests\Unit\Forms\Fixtures;

use Coretik\Services\Forms\Core\Exception;
use Coretik\Services\Forms\Tunnel\Step\SummaryStep;
use Coretik\Services\Forms\Tunnel\TunnelForm;

class RegistrationTunnel extends TunnelForm
{
    const ID = 'registration';

    public int $runs = 0;
    public array $ranWith = [];
    public ?string $failWith = null;

    protected function buildSteps(): void
    {
        $this
            ->addStep(ContactStep::make($this))
            ->addStep(NameStep::make($this))
            ->addStep(SummaryStep::make($this));
    }

    protected function isValidContext(): bool
    {
        return true;
    }

    protected function run(): void
    {
        $this->runs++;
        $this->ranWith = $this->getValues();
        if (null !== $this->failWith) {
            throw new Exception($this->failWith);
        }
    }
}
