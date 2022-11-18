<?php

namespace Coretik\Services\Notices;

class NoticeError extends Notice
{
    const TYPE = 'error';

    public function __construct(string $message)
    {
        parent::__construct($message, [$this, 'render']);
    }

    /**
     * Common wp-admin render.
     * Up to you to create your own rendering method in a custom Observer.
     */
    public function render()
    {
        ?>
        <div class="notice notice-error is-dismissible">
            <p><?= $this->message ?></p>
        </div>
        <?php
    }
}
