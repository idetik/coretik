<?php

// phpcs:ignoreFile -- WordPress tests bootstrap

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

/*
 * Database settings come from the environment (CI), with defaults for the local Docker database:
 * docker run -d --name coretik-tests-mysql -e MYSQL_ROOT_PASSWORD=root -e MYSQL_DATABASE=coretik_tests -p 3307:3306 --tmpfs /var/lib/mysql mysql:8.4
 */
\Coretik\Testing\Bootstrap::make(dirname(__DIR__, 2))
    ->database('coretik_tests')
    ->define('WP_TESTS_TITLE', 'Coretik Tests')
    // Boot coretik and declare the fixtures, as a theme would do (pluggable functions are needed)
    ->on('after_setup_theme', function () {
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

        // Scheduled events: "starts" / "ends" model events at the dates of the metas starts_at / ends_at
        \Coretik\Core\Builders\PostType::make('session')
            ->setSingularName('Session')
            ->setPluralName('Sessions')
            ->handler(
                \Coretik\Core\Models\Handlers\ScheduledEventsHandler::make()
                    ->at('starts', fn ($model) => get_post_meta($model->id(), 'starts_at', true))
                    ->at('ends', fn ($model) => (int)get_post_meta($model->id(), 'ends_at', true))
                    ->when(fn ($model, $event) => !get_post_meta($model->id(), 'cancelled', true))
            )
            ->addToSchema();

        // Endpoints: /event/{post}/inscription/ (also declared by "course"), /event/{post}/billet/{value}/
        \Coretik\Core\Builders\PostType::make('event')
            ->setSingularName('Event')
            ->setPluralName('Events')
            ->handler(
                \Coretik\Core\Models\Handlers\EndpointHandler::make('inscription')
                    ->template('event-registration.php')
                    ->noIndex()
                    ->hideAdminBar()
            )
            ->handler(\Coretik\Core\Models\Handlers\EndpointHandler::make('billet')->acceptsValue())
            ->addToSchema();

        \Coretik\Core\Builders\PostType::make('course')
            ->setSingularName('Course')
            ->setPluralName('Courses')
            ->handler(\Coretik\Core\Models\Handlers\EndpointHandler::make('inscription'))
            ->addToSchema();

        // Typed accessors of the schema (SchemaAccessorsTest)
        \Coretik\Core\Builders\Taxonomy::make('genre', 'event')
            ->setSingularName('Genre')
            ->setPluralName('Genres')
            ->addToSchema();

        \Coretik\Core\Builders\UserType::make('coach', 'Coach', ['read' => true])->addToSchema();
    })
    ->run();
