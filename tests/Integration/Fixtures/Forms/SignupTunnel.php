<?php

namespace Coretik\Tests\Integration\Fixtures\Forms;

use Coretik\Services\Forms\Tunnel\Step\SummaryStep;
use Coretik\Services\Forms\Tunnel\TunnelForm;

/**
 * Tunnel without template in the test theme: rendered by the Coretik default views.
 * Its submissions are recorded in the "signups" option.
 */
class SignupTunnel extends TunnelForm
{
    const ID = 'signup';

    protected function buildSteps(): void
    {
        $this->addStep(SignupStep::make($this))->addStep(SummaryStep::make($this));
    }

    protected function isValidContext(): bool
    {
        return true;
    }

    protected function run(): void
    {
        \update_option('signups', \array_merge((array)\get_option('signups', []), [$this->getValues()]));
    }
}
