<?php

namespace Coretik\Tests\Unit\Migrations;

use Coretik\Services\Migrations\Migration;

class CrashingMigration extends Migration
{
    const MIGRATION_KEY = 'crashing';

    public function migrate()
    {
        throw new \RuntimeException('Unexpected');
    }
}
