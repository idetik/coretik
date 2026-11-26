<?php

/**
 * Steps of the tunnel: one <fieldset data-form-step> per step, rendered by StepInterface::render().
 * The scripts set the height of [data-form-steps] to the height of the current step (animations).
 *
 * @var \Coretik\Services\Forms\Tunnel\TunnelForm $form
 * @var \Coretik\Services\Forms\Tunnel\Step\StepInterface[] $steps
 * @var int $currentStep 1-based
 */

$total = count($steps);

?>
<div class="block-form-fields" data-form-steps>
    <?php
    foreach ($steps as $i => $step) :
        $stepNumber = $i + 1;
        ?>
        <fieldset
            id="<?= esc_attr($form->uniqid() . '-step-' . $stepNumber) ?>"
            class="block-form-fieldset"
            data-step="<?= (int)$stepNumber ?>"
            data-form-step
            <?= $step->getSubmitLabel() ? 'data-form-step-submit-text="' . esc_attr($step->getSubmitLabel()) . '"' : '' ?>
            <?= $step->isSummary() ? 'data-form-step-summary' : '' ?>
            <?= $stepNumber !== $currentStep ? 'hidden' : '' ?>
            >
            <legend class="block-form-fieldset__legend" data-step-title>
                <span class="visuallyhidden"><?= esc_html(sprintf(__('Étape %1$d/%2$d :', 'coretik'), $stepNumber, $total)) ?> </span>
                <?= esc_html($step->getTitle()) ?>
            </legend>
            <?php if ($step->getInstructions()) : ?>
                <p class="block-form-fieldset__description"><?= wp_kses_post($step->getInstructions()) ?></p>
            <?php endif; ?>
            <?php $step->render() ?>
        </fieldset>
    <?php endforeach; ?>
</div>
