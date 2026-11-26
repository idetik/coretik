<?php

namespace Coretik\Tests\Unit\Migrations;

use Coretik\Services\Migrations\Migration;

class CountingMigration extends Migration
{
    const MIGRATION_KEY = 'counting';
    const DESCRIPTION = 'Counts its runs';

    /** @var string[] keys of the migrations run, in order */
    public static array $runs = [];

    public function migrate()
    {
        static::$runs[] = $this->getKey();
        $this->log('Run <b>' . count(static::$runs) . '</b>');
    }
}
