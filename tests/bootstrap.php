<?php

// phpcs:ignoreFile -- WP_Error stub mirrors WordPress naming

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Minimal WP_Error stub: unit tests run without WordPress
if (!class_exists('WP_Error')) {
    class WP_Error
    {
        public function __construct(public $code = '', public $message = '')
        {
        }

        public function get_error_message()
        {
            return $this->message;
        }
    }
}

if (!class_exists('WP_Post')) {
    class WP_Post
    {
        public $ID = 0;
    }
}

foreach (['MINUTE_IN_SECONDS' => 60, 'HOUR_IN_SECONDS' => 3600, 'DAY_IN_SECONDS' => 86400] as $constant => $value) {
    if (!defined($constant)) {
        define($constant, $value);
    }
}
