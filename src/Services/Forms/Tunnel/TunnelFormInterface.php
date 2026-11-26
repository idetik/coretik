<?php

namespace Coretik\Services\Forms\Tunnel;

use Coretik\Services\Forms\Tunnel\Step\StepInterface;
use Illuminate\Support\Collection;

/**
 * The tunnel seen by its steps.
 */
interface TunnelFormInterface
{
    public function id();
    public function getRules(): array;
    public function getFields(): array;
    public function hasField(string $fieldName): bool;
    public function fieldLabel($field);
    public function getFormName();
    public function getTemplate();
    public function viewPart($part, array $data = [], bool $return = false);
    public function sanitizeFormFields(array $posted): array;
    public function getStepBefore(StepInterface $step): ?StepInterface;

    /**
     * @return Collection<int, StepInterface>
     */
    public function steps(): Collection;
    public function hasStep(string|StepInterface $stepClassOrName): bool;
    public function payload(): array;
    public function setSteps(array $steps): self;
}
