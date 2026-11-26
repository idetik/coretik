<?php

namespace Coretik\Tests\Unit\Migrations;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Coretik\Services\Migrations\Migrations;
use Coretik\Services\Migrations\Result;
use Coretik\Tests\TestCase;

class MigrationsTest extends TestCase
{
    private MemoryStatusStore $store;
    private Migrations $migrations;

    protected function setUp(): void
    {
        parent::setUp();
        CountingMigration::$runs = [];
        $this->store = new MemoryStatusStore();
        $this->migrations = (new Migrations($this->store))
            ->add(CountingMigration::class, new CountingSeeder(), OtherMigration::class);
    }

    public function testMigrationsByKeyInTheOrderTheyWereAdded(): void
    {
        $this->assertSame(['counting', 'seeder', 'other'], $this->migrations->keys());
        $this->assertInstanceOf(CountingMigration::class, $this->migrations->get('counting'));
        $this->assertSame($this->migrations->get('counting'), $this->migrations->migrations()['counting'], 'Built once');
        $this->assertTrue($this->migrations->has('seeder'));
        $this->assertFalse($this->migrations->has('unknown'));
        $this->assertTrue($this->migrations->isSeeder('seeder'));
        $this->assertFalse($this->migrations->isSeeder('counting'));
        $this->assertSame('Counts its runs', $this->migrations->description('counting'));
    }

    public function testUnknownKeyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown migration "unknown" (counting, seeder, other)');
        $this->migrations->get('unknown');
    }

    public function testDuplicateKeyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Migration key "counting" is already used');
        $this->migrations->add(new CountingMigration())->keys();
    }

    public function testMigrationWithoutKeyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->migrations->add(KeylessMigration::class)->keys();
    }

    public function testPendingMigrationsExcludeSeedersAndDoneMigrations(): void
    {
        $this->assertSame(['counting', 'other'], $this->migrations->pending());

        $this->store->set('counting', Result::DONE);
        $this->store->set('other', Result::FAILED, 'Error');

        $this->assertSame(['other'], $this->migrations->pending(), 'A failed migration is still pending');
    }

    public function testRunRecordsTheStatusAndReturnsTheLogsOfTheRun(): void
    {
        Actions\expectDone('coretik/migrations/running')->twice();
        Actions\expectDone('coretik/migrations/done')->twice();

        $result = $this->migrations->run('counting');

        $this->assertTrue($result->succeeded());
        $this->assertSame([['message' => 'Run <b>1</b>', 'type' => 'success']], $result->logs);
        $this->assertSame(Result::DONE, $this->migrations->status('counting')['status']);
        $this->assertTrue($this->migrations->isDone('counting'));

        $again = $this->migrations->run('counting', true);
        $this->assertSame([['message' => 'Run <b>2</b>', 'type' => 'success']], $again->logs, 'Only the logs of this run');
    }

    public function testMigrationRunsOnceUnlessForced(): void
    {
        $this->migrations->run('counting');
        $skipped = $this->migrations->run('counting');

        $this->assertSame(Result::SKIPPED, $skipped->status);
        $this->assertSame(['counting'], CountingMigration::$runs);

        $this->assertTrue($this->migrations->run('counting', true)->succeeded());
        $this->assertSame(['counting', 'counting'], CountingMigration::$runs);
    }

    public function testSeederRunsEachTime(): void
    {
        $this->migrations->run('seeder');
        $this->migrations->run('seeder');

        $this->assertSame(['seeder', 'seeder'], CountingMigration::$runs);
    }

    public function testMigrationErrorFailsTheMigration(): void
    {
        Actions\expectDone('coretik/migrations/failed')->once();
        $this->migrations->add(FailingMigration::class);

        $result = $this->migrations->run('failing');

        $this->assertTrue($result->failed());
        $this->assertSame('Nothing to convert', $result->message);
        $this->assertSame([['message' => 'Before the failure', 'type' => 'error']], $result->logs);
        $this->assertSame(['status' => Result::FAILED, 'message' => 'Nothing to convert'], array_diff_key($this->migrations->status('failing'), ['ran_at' => 0]));
        $this->assertContains('failing', $this->migrations->pending());
    }

    public function testUnexpectedErrorIsRecordedThenThrown(): void
    {
        Actions\expectDone('coretik/migrations/failed')->once();
        $this->migrations->add(CrashingMigration::class);

        try {
            $this->migrations->run('crashing');
            $this->fail('The exception is thrown');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Unexpected', $exception->getMessage());
        }

        $this->assertSame('RuntimeException: Unexpected', $this->migrations->status('crashing')['message']);
    }

    public function testMigrateRunsThePendingMigrationsAndStopsAtTheFirstFailure(): void
    {
        $migrations = (new Migrations($this->store))->add(CountingMigration::class, CountingSeeder::class, FailingMigration::class, OtherMigration::class);

        $results = $migrations->migrate();

        $this->assertSame(['counting', 'failing'], array_map(fn (Result $result) => $result->key, $results));
        $this->assertSame(['counting', 'failing'], CountingMigration::$runs, 'Seeder never run by migrate(), other not run after the failure');
        $this->assertSame([], (new Migrations(new MemoryStatusStore()))->migrate());
    }

    public function testRunManyChecksTheKeysBeforeAnyRun(): void
    {
        try {
            $this->migrations->runMany(['counting', 'unknown']);
            $this->fail('Unknown key');
        } catch (\InvalidArgumentException) {
            $this->assertSame([], CountingMigration::$runs);
        }

        $results = $this->migrations->runMany(['seeder', 'counting', 'seeder']);
        $this->assertCount(2, $results);
        $this->assertSame(['seeder', 'counting'], CountingMigration::$runs);
    }

    public function testMarkDoneAndReset(): void
    {
        $this->migrations->markDone(['counting', 'other']);

        $this->assertSame([], $this->migrations->pending());
        $this->assertSame('Marked as done', $this->migrations->status('other')['message']);
        $this->assertSame([], CountingMigration::$runs);

        $this->migrations->reset('other');
        $this->assertSame(['other'], $this->migrations->pending());
    }

    public function testListenRegistersTheBackOfficePage(): void
    {
        Functions\when('is_admin')->justReturn(true);

        $this->migrations->listen();

        $this->assertNotFalse(has_action('admin_menu', 'Coretik\Services\Migrations\Admin->menu()'));
        $this->assertNotFalse(has_action('admin_post_coretik_migrations_run', 'Coretik\Services\Migrations\Admin->run()'));
        $this->assertSame([], CountingMigration::$runs);
    }

    public function testListenWithoutBackOfficePage(): void
    {
        Functions\expect('is_admin')->never();

        $this->migrations->admin(false)->cli(false)->listen();

        $this->assertFalse(has_action('admin_menu', 'Coretik\Services\Migrations\Admin->menu()'));
    }
}
