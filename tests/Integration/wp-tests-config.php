<?php

// phpcs:ignoreFile -- WordPress tests configuration

/*
 * Database settings come from the environment (CI), with defaults for the local Docker database:
 * docker run -d --name coretik-tests-mysql -e MYSQL_ROOT_PASSWORD=root -e MYSQL_DATABASE=coretik_tests -p 3307:3306 --tmpfs /var/lib/mysql mysql:8.4
 */
define('DB_NAME', getenv('WP_TESTS_DB_NAME') ?: 'coretik_tests');
define('DB_USER', getenv('WP_TESTS_DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('WP_TESTS_DB_PASSWORD') ?: 'root');
define('DB_HOST', getenv('WP_TESTS_DB_HOST') ?: '127.0.0.1:3307');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

define('ABSPATH', dirname(__DIR__, 2) . '/vendor/johnpbloch/wordpress-core/');

define('WP_DEFAULT_THEME', 'default');
define('WP_DEBUG', true);

define('WP_TESTS_DOMAIN', 'example.org');
define('WP_TESTS_EMAIL', 'admin@example.org');
define('WP_TESTS_TITLE', 'Coretik Tests');
define('WP_PHP_BINARY', 'php');
define('WPLANG', '');

$table_prefix = 'wptests_';
