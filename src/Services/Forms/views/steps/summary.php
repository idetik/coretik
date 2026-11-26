<?php

/**
 * Summary step (Tunnel\Step\SummaryStep): the values are (re)injected as plain text by
 * scripts/TunnelFormStep.js (summarize()) when the step is displayed. The server rendering is for the first display only.
 *
 * @var \Coretik\Services\Forms\Tunnel\TunnelForm $form
 * @var \Coretik\Services\Forms\Tunnel\Step\StepInterface $step
 */

$steps = $form->steps()->filter(fn ($item) => !$item->isSummary())->all();

$toText = function ($value): string {
    if (is_array($value)) {
        return ($value['type'] ?? null) === 'file' ? (string)($value['name'] ?? '') : implode(', ', array_map('strval', $value));
    }
    return (string)$value;
};

?>
<ul class="block-form-summary">
    <?php
    foreach ($steps as $summaryStep) :
        $values = array_filter($summaryStep->getValues(), fn ($value) => !in_array($value['value'], [null, '', []], true));
        $number = $summaryStep->getStepIndex() + 1;
        ?>
        <li class="block-form-summary__item">
            <div class="block-form-summary-step" data-summary-step="<?= (int)$number ?>">
                <span class="block-form-summary-step__index"><?= (int)$number ?></span>
                <span class="block-form-summary-step__title"><?= esc_html($summaryStep->getTitle()) ?></span>
                <?php if (count($values) === 1) : ?>
                    <span class="block-form-summary-step__value"><?= esc_html($toText(reset($values)['value'])) ?></span>
                <?php elseif (count($values) > 1) : ?>
                    <ul class="block-form-summary-step__values">
                        <?php foreach ($values as $value) : ?>
                            <li>
                                <span class="block-form-summary-step__key"><?= esc_html($value['label']) ?></span>
                                <span class="block-form-summary-step__value"><?= esc_html($toText($value['value'])) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <button
                    type="button"
                    data-go-to-step="<?= (int)$number ?>"
                    aria-controls="<?= esc_attr($form->uniqid() . '-step-' . $number) ?>"
                    class="block-form-summary-step__action"
                    >
                    <?= esc_html__('Modifier', 'coretik') ?>
                    <span class="visuallyhidden"><?= esc_html(sprintf(__('l\'étape %1$d : %2$s', 'coretik'), $number, $summaryStep->getTitle())) ?></span>
                </button>
            </div>
        </li>
    <?php endforeach; ?>
</ul>
