<?php

namespace Coretik\Services\Migrations;

/**
 * Expected failure of a migration: its message is shown, the migration is recorded as failed and can run again
 */
class MigrationErrorException extends \Exception
{
}
