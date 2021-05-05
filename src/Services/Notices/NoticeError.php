<?php

namespace Coretik\Services\Notices;

class NoticeError extends Notice
{
    public function __construct(string $message)
    {
        parent::__construct($message, [$this, 'render']);
    }

    public function render()
    {
        ?>
        <div class="notice notice-error is-dismissible">
            <p><?= $this->message ?></p>
        </div>
        <?php
    }
}
