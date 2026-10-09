<?php

// phpcs:ignoreFile -- WordPress tests bootstrap

putenv('WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php');

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

$wpTests = getenv('WP_PHPUNIT__DIR') ?: dirname(__DIR__, 2) . '/vendor/wp-phpunit/wp-phpunit';

require_once $wpTests . '/includes/functions.php';

// Boot coretik and declare the fixtures, as a theme would do (pluggable functions are needed)
tests_add_filter('after_setup_theme', function () {
    \Coretik\App::run([]);

    \Coretik\Core\Builders\PostType::make('product')
        ->setSingularName('Product')
        ->setPluralName('Products')
        ->factory(\Coretik\Tests\Integration\Fixtures\ProductModel::class)
        ->handler(\Coretik\Core\Models\Handlers\TriggerModelHooksHandler::class)
        ->addToSchema();
});
require_once $wpTests . '/includes/bootstrap.php';
