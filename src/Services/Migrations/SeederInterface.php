<?php

namespace Coretik\Services\Migrations;

/**
 * Run on demand only (deployment, test data, cache clear…): never by Migrations::migrate(), always when asked,
 * even after a successful run.
 */
interface SeederInterface extends MigrationInterface
{
}
