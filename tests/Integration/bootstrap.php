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

    // Relationships: tutor (user) 1-n player (post_author) 1-n registration (post_parent)
    // camp 1-n registration (shadow taxonomy)
    coretik()->schema('users', 'user')->factory(\Coretik\Tests\Integration\Fixtures\TutorModel::class);

    \Coretik\Core\Builders\PostType::make('camp')
        ->setSingularName('Camp')
        ->setPluralName('Camps')
        ->factory(\Coretik\Tests\Integration\Fixtures\CampModel::class)
        ->addToSchema();

    \Coretik\Core\Builders\PostType::make('player')
        ->setSingularName('Player')
        ->setPluralName('Players')
        ->factory(\Coretik\Tests\Integration\Fixtures\PlayerModel::class)
        ->handler(
            \Coretik\Core\Models\Handlers\Relationship\BelongsToHandler::withPivot('tutor_id')
                ->setBelongsToBuilder(\Coretik\Tests\Integration\Fixtures\TutorModel::class)
        )
        ->addToSchema();

    \Coretik\Core\Builders\PostType::make('registration')
        ->setSingularName('Registration')
        ->setPluralName('Registrations')
        ->factory(\Coretik\Tests\Integration\Fixtures\RegistrationModel::class)
        ->handler(
            \Coretik\Core\Models\Handlers\Relationship\BelongsToHandler::withPivot('player_id')
                ->setBelongsToBuilder('player')
        )
        ->handler(
            \Coretik\Core\Models\Handlers\Relationship\ShadowTaxonomyHandler::withPivot('camp_id')
                ->setBelongsToBuilder('camp')
        )
        ->addToSchema();
});
require_once $wpTests . '/includes/bootstrap.php';
