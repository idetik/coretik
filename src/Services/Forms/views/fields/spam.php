<?php

/**
 * Anti-spam honeypot (Form::honeyPotChecked()): a checkbox hidden off screen, checked by robots only.
 * Override: templates/forms/fields/spam.php in the theme.
 */

$uniqid = uniqid('form-confirm-');

?>
<div class="form-coretik-confirm" style="left: -999em; position: absolute;" aria-hidden="true">
    <label for="<?= esc_attr($uniqid) ?>">Confirm</label>
    <input type="checkbox" name="form_coretik_confirm" id="<?= esc_attr($uniqid) ?>" tabindex="-1" autocomplete="off">
</div>
