<?php

namespace Coretik\Services\Forms\Tunnel\Step;

use Coretik\Services\Forms\Core\Validation\Validation;

/**
 * Step of a tunnel form (Tunnel\TunnelForm). Implemented by Tunnel\FormStep.
 */
interface StepInterface
{
    public function getRules(): array;
    public function getTitle(): string;
    public function getInstructions(): string;
    public function getSubmitLabel(): ?string;

    /**
     * 0-based index (the DOM uses data-step = index + 1).
     */
    public function getStepIndex(): int;
    public function isOpen(): bool;
    public function isValid(): bool;
    public function isTheLastStep(): bool;
    public function isSummary(): bool;
    public function validate(): self;

    /**
     * @return array<int, array{step: int, field: string, messages: string[]}>
     */
    public function getErrors(): array;

    /**
     * @return array<int, array{step: int, field: string, label: string, private: bool, value: mixed}>
     */
    public function getValues(): array;
    public function getValidation(): ?Validation;

    /**
     * @return StepInterface[]
     */
    public function getDependencies(): array;
    public function withDependencies(array $dependencies): self;
    public function addDependency(StepInterface $step): self;

    /**
     * Validated values: [field => value]
     */
    public function payload(): array;

    /**
     * Non blocking warnings sent once the step is valid.
     *
     * @return string[]
     */
    public function notices(): array;

    /**
     * @return array<string, string[]> category => labels of the fields
     */
    public function getRgpdDataCategories(): array;
    public function render(bool $return = false): ?string;
}
