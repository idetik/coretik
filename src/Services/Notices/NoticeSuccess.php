<?php

namespace Coretik\Services\Notices;

class NoticeSuccess extends Notice
{
    const TYPE = 'success';

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
        <div class="notice notice-success is-dismissible">
            <p><?= \wp_kses_post($this->message) ?></p>
        </div>
        <?php
    }
}
