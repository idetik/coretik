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
