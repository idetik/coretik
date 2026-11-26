<?php

// phpcs:ignoreFile -- WP-CLI stubs: unit tests run without WP-CLI

namespace {
    if (!class_exists('WP_CLI')) {
        /**
         * Records the output; error() throws as WP-CLI exits
         */
        class WP_CLI
        {
            /** @var array<int, array{0: string, 1: string}> [method, message] */
            public static array $output = [];

            /** @var array<int, array{0: string, 1: mixed, 2: array}> */
            public static array $commands = [];

            public static function add_command($name, $callable, $args = [])
            {
                static::$commands[] = [$name, $callable, $args];
            }

            public static function log($message)
            {
                static::$output[] = ['log', $message];
            }

            public static function success($message)
            {
                static::$output[] = ['success', $message];
            }

            public static function warning($message)
            {
                static::$output[] = ['warning', $message];
            }

            public static function error($message)
            {
                static::$output[] = ['error', $message];
                throw new \WP_CLI_Stub_Exit($message);
            }
        }

        class WP_CLI_Stub_Exit extends \RuntimeException
        {
        }
    }
}

namespace WP_CLI\Utils {
    if (!function_exists('WP_CLI\Utils\format_items')) {
        function format_items($format, $items, $fields)
        {
            \WP_CLI::$output[] = ['table', ['format' => $format, 'items' => $items, 'fields' => $fields]];
        }
    }
}
