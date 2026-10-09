<?php

namespace Coretik\Tests\Integration;

use Coretik\Core\Builders\BuilderModelable;
use Coretik\Core\Query\QueryCache;

/**
 * WordPress rolls back the database after each test: coretik static caches are reset too.
 */
abstract class IntegrationTestCase extends \WP_UnitTestCase
{
    public function set_up(): void
    {
        parent::set_up();
        (new \ReflectionProperty(BuilderModelable::class, 'models'))->setValue(null, []);
        (new \ReflectionProperty(QueryCache::class, 'instance'))->setValue(null, null);
    }
}
