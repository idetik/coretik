<?php

use Soreno\App;

?>

<div class="modal-container <?= App::modals()->hasOpen() ? 'modal--open' : '' ?>">
    <div class="modal-overlay"></div>
    <?php App::modals()->render(); ?>
</div>
