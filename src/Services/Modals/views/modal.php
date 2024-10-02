<div
    class="modal <?= $is_large ? 'modal--large' : '' ?>"
    id="<?= $id ?>"
    role="dialog"
    <?= !empty($title) ? 'aria-labelledby="' . $id . '-title"' : '' ?>
    aria-modal="true"
    data-modal-keep="<?= $keep ? 'true' : 'false' ?>"
    aria-hidden="<?= $is_open ? 'false' : 'true' ?>"
    <?= $is_open ? '' : 'hidden' ?>
    >
    <?php if ($is_closable) : ?>
        <button class="modal__close" data-modal-close="<?= $id ?>">
            <span class="modal-close-icon" aria-hidden="true"></span>
            <span class="visuallyhidden">Fermer la fenêtre</span>
        </button>
    <?php endif; ?>

    <?php if (!empty($title)) : ?>
        <div id="<?= $id ?>-title" role="heading" aria-level="2" class="modal__title">
        <?= $title ?>
        </div>
    <?php endif; ?>

    <div class="modal__body">
        <?= $body ?>
    </div>
</div>
