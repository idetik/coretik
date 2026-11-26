<?php

/**
 * Default template of a Coretik\Services\Forms\Tunnel\TunnelForm (TunnelForm::TEMPLATE_FALLBACK).
 * Override: templates/forms/tunnel-form.php for every tunnel, templates/forms/{ID}.php (or TEMPLATE) for one.
 *
 * @var \Coretik\Services\Forms\Tunnel\TunnelForm $form
 * @var \Illuminate\Support\Collection $steps
 * @var int $currentStep 1-based
 */

$currentStep = $currentStep ?? 1;
$parts = ['currentStep' => $currentStep, 'steps' => $steps];

$form->viewPart('parts/json-form-open', [
    'class' => 'block-form__form',
    'enctype' => 'multipart/form-data',
    'data_atts' => ['data-current-step="' . (int)$currentStep . '"'],
]);

    $form->viewPart('parts/tunnel-timeline', $parts);
    $form->viewPart('parts/tunnel-steps', $parts);
    $form->viewPart('parts/tunnel-actions', $parts);

$form->viewPart('parts/json-form-close');
