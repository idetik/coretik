<?php

/**
 * @var \Coretik\Services\Email\Viewer\Viewer $viewer
 * @var \Coretik\Services\Email\Viewer\Catalog $catalog
 * @var array<string, \Coretik\Services\Email\Viewer\Entry[]> $groups
 * @var ?\Coretik\Services\Email\Viewer\Entry $entry
 * @var ?\Coretik\Services\Email\Viewer\Preview $preview
 * @var array<string, string> $query context fields of the query string
 * @var array $context
 * @var ?bool $sent result of a test sending
 */

$contextFields = $catalog->contextFields($context);
$envelope = $preview ? array_filter([
    'De' => $preview->header('From'),
    'Répondre à' => $preview->header('Reply-To'),
    'À' => $preview->recipients(),
    'Objet' => $preview->subject,
]) : [];
?>
<div class="wrap coretik-email-viewer">
    <h1 class="wp-heading-inline"><?= \esc_html($viewer->option('title')) ?></h1>
    <hr class="wp-header-end">

    <?php if (null !== $sent) : ?>
        <div class="notice notice-<?= $sent ? 'success' : 'error' ?> is-dismissible">
            <p><?= \esc_html($sent
                ? sprintf('Email de test envoyé à %s.', \wp_get_current_user()->user_email)
                : 'L\'email de test n\'a pas pu être envoyé.') ?></p>
        </div>
    <?php endif; ?>

    <?php if (!$entry) : ?>
        <p>Aucun email déclaré.</p>
    <?php else : ?>
        <div class="coretik-email-viewer__layout">
            <nav class="coretik-email-viewer__list" aria-label="Emails">
                <?php foreach ($groups as $group => $items) : ?>
                    <?php if ('' !== $group) : ?>
                        <h2 class="coretik-email-viewer__group"><?= \esc_html($group) ?></h2>
                    <?php endif; ?>
                    <ul>
                        <?php foreach ($items as $item) : ?>
                            <li>
                                <a
                                    class="coretik-email-viewer__item<?= $item->key === $entry->key ? ' is-current' : '' ?>"
                                    href="<?= \esc_url($viewer->url(['mail' => $item->key] + $query)) ?>"
                                    <?= $item->key === $entry->key ? 'aria-current="page"' : '' ?>
                                >
                                    <strong><?= \esc_html($item->label) ?></strong>
                                    <?php if ($item->trigger) : ?>
                                        <span><?= \esc_html($item->trigger) ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endforeach; ?>
            </nav>

            <section class="coretik-email-viewer__main" aria-live="polite">
                <header class="coretik-email-viewer__header">
                    <div>
                        <h2><?= \esc_html($entry->label) ?></h2>
                        <?php if ($entry->description) : ?>
                            <p class="description"><?= \esc_html($entry->description) ?></p>
                        <?php endif; ?>
                    </div>
                    <code><?= \esc_html($entry->key) ?></code>
                </header>

                <div class="coretik-email-viewer__toolbar">
                    <?php if ($contextFields) : ?>
                        <form method="get" action="<?= \esc_url(\admin_url('admin.php')) ?>" class="coretik-email-viewer__context">
                            <input type="hidden" name="page" value="<?= \esc_attr($viewer->option('slug')) ?>">
                            <input type="hidden" name="mail" value="<?= \esc_attr($entry->key) ?>">
                            <?= $contextFields ?>
                            <button type="submit" class="button">Appliquer</button>
                        </form>
                    <?php endif; ?>
                    <div class="coretik-email-viewer__actions">
                        <div class="coretik-email-viewer__devices" role="group" aria-label="Largeur d'affichage">
                            <button type="button" class="button is-active" data-coretik-email-width="" aria-pressed="true">Ordinateur</button>
                            <button type="button" class="button" data-coretik-email-width="375" aria-pressed="false">Mobile</button>
                        </div>
                        <?php if ($preview) : ?>
                            <a class="button" href="<?= \esc_url($viewer->frameUrl($entry->key, $query)) ?>" target="_blank">Ouvrir dans un onglet</a>
                            <a class="button button-primary" href="<?= \esc_url($viewer->sendUrl($entry->key, $query)) ?>">M'envoyer un test</a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($preview) : ?>
                    <dl class="coretik-email-viewer__envelope">
                        <?php foreach ($envelope as $label => $value) : ?>
                            <div><dt><?= \esc_html($label) ?> :</dt><dd><?= \esc_html($value) ?></dd></div>
                        <?php endforeach; ?>
                    </dl>
                    <div class="coretik-email-viewer__stage">
                        <iframe
                            class="coretik-email-viewer__frame"
                            src="<?= \esc_url($viewer->frameUrl($entry->key, $query)) ?>"
                            title="<?= \esc_attr(sprintf('Aperçu : %s', $entry->label)) ?>"
                            sandbox="allow-same-origin allow-popups"
                        ></iframe>
                    </div>
                <?php else : ?>
                    <div class="notice notice-warning inline"><p>Aperçu indisponible : les données d'exemple nécessaires manquent.</p></div>
                <?php endif; ?>
            </section>
        </div>
    <?php endif; ?>
</div>
<script>
    (function () {
        var frame = document.querySelector('.coretik-email-viewer__frame');
        if (!frame) {
            return;
        }

        // Height of the email: no inner scrollbar
        function fit() {
            try {
                frame.style.height = frame.contentDocument.documentElement.scrollHeight + 'px';
            } catch (e) {}
        }
        frame.addEventListener('load', fit);

        document.querySelectorAll('[data-coretik-email-width]').forEach(function (button, index, buttons) {
            button.addEventListener('click', function () {
                var width = button.getAttribute('data-coretik-email-width');
                frame.style.width = width ? width + 'px' : '';
                buttons.forEach(function (other) {
                    other.classList.toggle('is-active', other === button);
                    other.setAttribute('aria-pressed', other === button ? 'true' : 'false');
                });
                setTimeout(fit, 50);
            });
        });
    })();
</script>
