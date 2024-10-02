<div
    id="modals-container"
    class="<?= app()->modals()->hasOpen() ? 'has-modal' : '' ?>"
    role="presentation"
    >
    <div id="modals-backdrop" aria-hidden="true"></div>
    <?php app()->modals()->render(); ?>
</div>
