<?php

namespace Coretik\Tests\Unit\Migrations;

use Coretik\Services\Migrations\Migration;
use Coretik\Services\Migrations\MigrationErrorException;

class FailingMigration extends Migration
{
    const MIGRATION_KEY = 'failing';

    public function migrate()
    {
        CountingMigration::$runs[] = $this->getKey();
        $this->log('Before the failure', 'error');
        throw new MigrationErrorException('Nothing to convert');
    }
}
