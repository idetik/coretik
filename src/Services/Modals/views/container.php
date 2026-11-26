<div
    id="modals-container"
    class="<?= coretik()->modals()->hasOpen() ? 'has-modal' : '' ?>"
    role="presentation"
    >
    <div id="modals-backdrop" aria-hidden="true"></div>
    <?php coretik()->modals()->render(); ?>
</div>
