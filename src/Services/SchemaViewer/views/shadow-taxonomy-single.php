<?php

/**
 * @var string $taxonomy Shadow taxonomy name
 * @var array{parent: string, child: string, pivot: string} $shadow
 */

$termsCount = \wp_count_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
?>

<div class="schema-data schema-data--shadow">
    <h4 class="schema-data__title">
        <span>
            <span class="dashicons dashicons-hidden" style="vertical-align:bottom"></span>&nbsp;<?= \esc_html($taxonomy) ?>
            <span class="schema-badge" title="Hidden taxonomy registered by ShadowTaxonomyHandler">Shadow</span>
        </span>
    </h4>
    <ul class="schema-data__tab">
        <li><b>Relation</b>: <code><?= \esc_html($shadow['parent']) ?></code> hasMany <code><?= \esc_html($shadow['child']) ?></code> (one term per <?= \esc_html($shadow['parent']) ?>)</li>
        <?php if ('' !== $shadow['pivot']) : ?>
            <li><b>Pivot</b>: <code><?= \esc_html($shadow['pivot']) ?></code></li>
        <?php endif; ?>
        <li><b>Terms</b>: <?= \is_numeric($termsCount) ? (int)$termsCount : '-' ?></li>
        <li><b>Visibility</b>: hidden (not public, no admin UI, no REST)</li>
    </ul>
</div>
