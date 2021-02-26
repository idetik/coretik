<div class="modal-content <?= $is_open ? '' : 'hidden' ?> <?= $is_large ? 'modal-content--large' : '' ?>" id="<?= $id ?>">
    <?php if ($is_closable) : ?>
        <div
            class="modal-content__close modal-discard"
            id="close-<?= $id ?>"
            >
            <i class="icon-clear"></i>
        </div>
    <?php endif; ?>
    <div class="modal-content__body">
        <?= $content ?>
    </div>
</div>
