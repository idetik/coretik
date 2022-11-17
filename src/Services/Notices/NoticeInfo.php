<?php

namespace Coretik\Services\Notices;

class NoticeInfo extends Notice
{
    const TYPE = 'info';

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
        <div class="notice notice-info is-dismissible">
            <p><?= $this->message ?></p>
        </div>
        <?php
    }
}
