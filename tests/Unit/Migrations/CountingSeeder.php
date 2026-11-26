<?php

namespace Coretik\Tests\Unit\Migrations;

use Coretik\Services\Migrations\Seeder;

class CountingSeeder extends Seeder
{
    const MIGRATION_KEY = 'seeder';

    public function migrate()
    {
        CountingMigration::$runs[] = $this->getKey();
        $this->log('Seeded &#x2714;', 'info');
    }
}
