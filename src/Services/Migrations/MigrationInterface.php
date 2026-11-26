<?php

namespace Coretik\Services\Migrations;

/**
 * A migration of the data of the application, identified by its key (wp app migrate <key>).
 *
 * Migrations run once: Migrations::migrate() (wp app migrate without key) runs those never done. Seeders
 * (SeederInterface) run on demand only, as many times as needed.
 */
interface MigrationInterface
{
    /**
     * Unique key: kebab-case, used on the command line and stored with the status
     */
    public function getKey(): string;

    /**
     * Does the job. Throws a MigrationErrorException to fail with a message.
     *
     * @return mixed
     */
    public function migrate();

    /**
     * Message shown after the run (back-office notice, WP-CLI line). Types: success, info, warning, error.
     */
    public function log(string $message, string $type = 'success'): static;

    /**
     * @return array<int, array{message: string, type: string}>
     */
    public function getLogs(): array;
}
