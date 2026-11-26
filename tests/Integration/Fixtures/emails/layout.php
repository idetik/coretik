<?php

/**
 * @var \Coretik\Tests\Integration\Fixtures\FixtureEmail $email
 */
?>
<html><head><style>.email-title { color: #023975; }</style></head><body>
<?php if ($email->preheader) : ?>
    <div class="email-preheader"><?= esc_html($email->preheader) ?></div>
<?php endif; ?>
<h1 class="email-title"><?= esc_html($email->email_title) ?></h1>
<?= $email->message ?>
</body></html>
