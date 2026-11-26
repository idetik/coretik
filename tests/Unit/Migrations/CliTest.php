<?php

namespace Coretik\Tests\Unit\Migrations;

use Brain\Monkey\Functions;
use Coretik\Services\Migrations\Cli;
use Coretik\Services\Migrations\Migrations;
use Coretik\Services\Migrations\Result;
use Coretik\Tests\TestCase;

require_once __DIR__ . '/Stubs/wp-cli.php';

/**
 * wp app migrate (WP-CLI is stubbed: output recorded, error() throws)
 */
class CliTest extends TestCase
{
    private MemoryStatusStore $store;
    private Migrations $migrations;
    private Cli $cli;

    protected function setUp(): void
    {
        parent::setUp();
        \WP_CLI::$output = [];
        \WP_CLI::$commands = [];
        CountingMigration::$runs = [];
        Functions\when('wp_strip_all_tags')->alias(fn ($text) => strip_tags((string)$text));
        Functions\when('wp_date')->alias(fn ($format, $timestamp) => gmdate($format, $timestamp));
        $this->store = new MemoryStatusStore();
        $this->migrations = (new Migrations($this->store))->add(CountingMigration::class, CountingSeeder::class, OtherMigration::class);
        $this->cli = new Cli($this->migrations);
    }

    /**
     * @return array<int, array{0: string, 1: mixed}>
     */
    private function output(): array
    {
        return \WP_CLI::$output;
    }

    public function testKeysRunInTheGivenOrder(): void
    {
        ($this->cli)(['seeder', 'counting']);

        $this->assertSame(['seeder', 'counting'], CountingMigration::$runs);
        $this->assertSame([
            ['log', 'Seeded ✔'],
            ['success', 'Migration seeder'],
            ['log', 'Run 2'],
            ['success', 'Migration counting'],
        ], $this->output());
    }

    public function testWithoutKeyRunsThePendingMigrations(): void
    {
        $this->store->set('counting', Result::DONE);

        ($this->cli)([]);

        $this->assertSame(['other'], CountingMigration::$runs);
    }

    public function testNothingToMigrate(): void
    {
        $this->migrations->markDone(['counting', 'other']);

        ($this->cli)([]);

        $this->assertSame([['success', 'Nothing to migrate.']], $this->output());
    }

    public function testMigrationDoneIsSkippedUnlessForced(): void
    {
        $this->store->set('counting', Result::DONE);

        ($this->cli)(['counting']);
        $this->assertSame([], CountingMigration::$runs);
        $this->assertSame('warning', $this->output()[0][0]);

        ($this->cli)(['counting'], ['force' => true]);
        $this->assertSame(['counting'], CountingMigration::$runs);
    }

    public function testUnknownKeyFailsBeforeAnyRun(): void
    {
        try {
            ($this->cli)(['counting', 'unknown']);
            $this->fail('WP_CLI::error()');
        } catch (\WP_CLI_Stub_Exit $exit) {
            $this->assertSame('Unknown migration "unknown" (counting, seeder, other)', $exit->getMessage());
        }

        $this->assertSame([], CountingMigration::$runs);
    }

    public function testFailureStopsWithAnError(): void
    {
        $this->migrations->add(FailingMigration::class);

        try {
            ($this->cli)(['failing', 'counting']);
            $this->fail('WP_CLI::error()');
        } catch (\WP_CLI_Stub_Exit $exit) {
            $this->assertSame('Migration failing failed: Nothing to convert', $exit->getMessage());
        }

        $this->assertSame(['failing'], CountingMigration::$runs);
        $this->assertSame(['warning', 'Before the failure'], $this->output()[0]);
    }

    public function testMarkDone(): void
    {
        ($this->cli)([], ['mark-done' => true]);

        $this->assertSame([], CountingMigration::$runs);
        $this->assertSame([], $this->migrations->pending());
        $this->assertSame([['success', '2 migration(s) marked as done: counting, other']], $this->output());
    }

    public function testList(): void
    {
        $this->store->statuses['counting'] = ['status' => Result::DONE, 'ran_at' => 0, 'message' => ''];

        ($this->cli)([], ['list' => true]);

        [$method, $table] = $this->output()[0];
        $this->assertSame('table', $method);
        $this->assertSame([
            ['key' => 'counting', 'type' => 'migration', 'status' => 'done', 'ran_at' => '1970-01-01 00:00:00', 'message' => '', 'description' => 'Counts its runs'],
            ['key' => 'seeder', 'type' => 'seeder', 'status' => '', 'ran_at' => '', 'message' => '', 'description' => ''],
            ['key' => 'other', 'type' => 'migration', 'status' => 'pending', 'ran_at' => '', 'message' => '', 'description' => ''],
        ], $table['items']);
        $this->assertSame([], CountingMigration::$runs);
    }

    /**
     * The WP_CLI constant would change the other tests (Cubi\is_cli())
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testListenRegistersTheCommand(): void
    {
        Functions\when('is_admin')->justReturn(false);
        define('WP_CLI', true);

        $this->migrations->listen();
        (new Migrations())->cli(false)->listen();

        $this->assertCount(1, \WP_CLI::$commands);
        [$name, $callable, $args] = \WP_CLI::$commands[0];
        $this->assertSame('app migrate', $name);
        $this->assertInstanceOf(Cli::class, $callable);
        $this->assertSame(['key', 'force', 'list', 'mark-done'], array_column($args['synopsis'], 'name'));
    }
}
