<?php

/**
 * Previous / Next buttons of the tunnel. The [data-next-step] button becomes type="submit"
 * on the submission step (summary, or last step without summary).
 *
 * @var \Coretik\Services\Forms\Tunnel\TunnelForm $form
 * @var \Illuminate\Support\Collection $steps
 * @var int $currentStep 1-based
 */

$step = $steps->get($currentStep - 1);
$hasSummary = $steps->contains(fn ($item) => $item->isSummary());
$isSubmitStep = $hasSummary ? $step->isSummary() : $currentStep === $steps->count();
$label = $step->getSubmitLabel() ?: ($isSubmitStep ? __('Envoyer', 'coretik') : __('Continuer', 'coretik'));

?>
<div class="block-form__buttons">
    <button
        type="button"
        class="block-form__button block-form__button--previous btn btn--link"
        data-previous-step
        <?= $currentStep === 1 ? 'hidden disabled' : '' ?>
        >
        <?= esc_html__('Retour', 'coretik') ?>
    </button>
    <button
        type="<?= $isSubmitStep ? 'submit' : 'button' ?>"
        class="block-form__button block-form__button--next btn btn--primary btn--progress <?= $isSubmitStep ? 'btn--submit' : 'btn--next' ?>"
        data-progress="<?= (int)ceil((100 * $currentStep) / max(1, $steps->count())) ?>"
        data-next-step
        >
        <?= esc_html($label) ?>
    </button>
</div>
