<?php

/**
 * Steps timeline ([data-form-navigation]), updated by scripts/TunnelFormNavigation.js.
 *
 * @var \Coretik\Services\Forms\Tunnel\TunnelForm $form
 * @var \Coretik\Services\Forms\Tunnel\Step\StepInterface[] $steps
 * @var int $currentStep 1-based
 */

$total = count($steps);

?>
<ol class="block-form__timeline" data-form-navigation>
    <?php
    foreach ($steps as $i => $step) :
        $stepNumber = $i + 1;
        $isCurrent = $stepNumber === $currentStep;
        $stepClasses = ['block-form-step'];
        $step->isValid() && $stepClasses[] = 'block-form-step--valid';
        $step->isOpen() && $stepClasses[] = 'block-form-step--selectable';
        $step->isTheLastStep() && $stepClasses[] = 'block-form-step--last';
        $step->isSummary() && $stepClasses[] = 'block-form-step--summary';
        $isCurrent && $stepClasses[] = 'block-form-step--current';
        ?>
        <li
            class="<?= esc_attr(implode(' ', $stepClasses)) ?>"
            data-form-navigation-step="<?= (int)$stepNumber ?>"
            data-form-navigation-item
            >
            <button
                type="button"
                class="block-form-step__number"
                data-go-to-step="<?= (int)$stepNumber ?>"
                <?= $step->isOpen() ? '' : 'disabled' ?>
                aria-expanded="<?= $isCurrent ? 'true' : 'false' ?>"
                <?= $isCurrent ? 'aria-current="step"' : '' ?>
                aria-controls="<?= esc_attr($form->uniqid() . '-step-' . $stepNumber) ?>"
                tabindex="<?= $isCurrent ? '0' : '-1' ?>"
                >
                <span class="block-form-step__index"><?= (int)$stepNumber ?></span>
                <span class="block-form-step__title"><?= esc_html($step->getTitle()) ?></span>
                <span class="visuallyhidden"><?= esc_html(sprintf(__('(étape %1$d sur %2$d)', 'coretik'), $stepNumber, $total)) ?></span>
            </button>
        </li>
    <?php endforeach; ?>
</ol>
