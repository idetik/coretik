<?php

namespace Coretik\Services\Migrations;

/**
 * Base migration: key from MIGRATION_KEY, optional DESCRIPTION, logs.
 *
 * class SplitAddress extends Migration
 * {
 *     const MIGRATION_KEY = 'split-address';
 *     const DESCRIPTION = 'Address split into street, postcode and city';
 *
 *     public function migrate()
 *     {
 *         // …
 *         $this->log(sprintf('%d address(es) split', $count));
 *     }
 * }
 */
abstract class Migration implements MigrationInterface
{
    public const MIGRATION_KEY = '';
    public const DESCRIPTION = '';

    /** @var array<int, array{message: string, type: string}> */
    protected array $logs = [];

    /**
     * @return mixed
     */
    abstract public function migrate();

    public function getKey(): string
    {
        return static::MIGRATION_KEY;
    }

    /**
     * Shown in the list of the migrations (WP-CLI, back-office)
     */
    public function getDescription(): string
    {
        return static::DESCRIPTION;
    }

    public function log(string $message, string $type = 'success'): static
    {
        $this->logs[] = ['message' => $message, 'type' => $type];
        return $this;
    }

    public function getLogs(): array
    {
        return $this->logs;
    }
}
