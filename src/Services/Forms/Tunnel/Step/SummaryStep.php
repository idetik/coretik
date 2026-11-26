<?php

namespace Coretik\Services\Forms\Tunnel\Step;

use Coretik\Services\Forms\Tunnel\FormStep;

/**
 * Generic summary step, without field. To add fields (message, consent...), extend it,
 * override getRules() and STEP_NAME (dedicated template).
 */
class SummaryStep extends FormStep implements SummaryStepInterface
{
    const STEP_NAME = 'summary';

    public function getRules(): array
    {
        return [];
    }

    protected function initializeStep(): void
    {
        $this->title = $this->title ?: \__('Récapitulatif', 'coretik');
        $this->instructions = $this->instructions ?: \__('Veuillez vérifier les informations saisies avant de valider votre demande.', 'coretik');
        $this->submitLabel ??= \__('Envoyer', 'coretik');
    }
}
