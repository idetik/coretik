<div class="modal-container <?= app()->modals()->hasOpen() ? 'modal--open' : '' ?>">
    <div class="modal-overlay"></div>
    <?php app()->modals()->render(); ?>
</div>
