<?php

namespace Coretik\Tests\Integration;

use Coretik\Core\Builders\Users\Registry;

/**
 * Roles are synced on admin requests only: Registry::persist() saves them from WP-CLI (database seeding)
 */
class RegistryPersistTest extends IntegrationTestCase
{
    public function testPersistSavesTheRolesOutsideOfTheAdmin(): void
    {
        \remove_role('coach');
        $this->assertFalse(\is_admin());

        Registry::instance()->persist();

        $this->assertNotNull(\get_role('coach'));
        $this->assertArrayHasKey('coach', \get_option(Registry::OPTION_KEY));
        $this->assertFalse(Registry::instance()->hasDiff());
    }
}
