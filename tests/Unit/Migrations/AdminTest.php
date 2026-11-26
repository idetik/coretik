<?php

namespace Coretik\Tests\Unit\Migrations;

use Brain\Monkey\Functions;
use Coretik\Services\Migrations\Admin;
use Coretik\Services\Migrations\Migrations;
use Coretik\Services\Migrations\Result;
use Coretik\Tests\TestCase;

class AdminTest extends TestCase
{
    private MemoryStatusStore $store;
    private Migrations $migrations;
    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        CountingMigration::$runs = [];
        $this->store = new MemoryStatusStore();
        $this->migrations = (new Migrations($this->store))->add(CountingMigration::class, CountingSeeder::class);
        $this->admin = new Admin($this->migrations);
    }

    public function testRows(): void
    {
        $this->store->set('counting', Result::DONE);

        $rows = $this->admin->rows();

        $this->assertSame(['counting', 'seeder'], array_column($rows, 'key'));
        $this->assertSame([false, true], array_column($rows, 'seeder'));
        $this->assertSame('Counts its runs', $rows[0]['description']);
        $this->assertSame(Result::DONE, $rows[0]['status']['status']);
        $this->assertNull($rows[1]['status']);
    }

    public function testHandleRunsAndStopsAtTheFirstFailure(): void
    {
        $this->migrations->add(FailingMigration::class);

        $results = $this->admin->handle(['seeder', 'failing', 'counting']);

        $this->assertSame([Result::DONE, Result::FAILED], array_map(fn (Result $result) => $result->status, $results));
        $this->assertSame(['seeder', 'failing'], CountingMigration::$runs);
    }

    public function testHandleReportsUnknownKeysAndUnexpectedErrors(): void
    {
        $this->migrations->add(CrashingMigration::class);

        $this->assertSame('Migration inconnue', $this->admin->handle(['unknown'])[0]->message);
        $this->assertSame('RuntimeException: Unexpected', $this->admin->handle(['crashing'])[0]->message);
    }

    public function testHandleSkipsDoneMigrationsUnlessForced(): void
    {
        $this->store->set('counting', Result::DONE);

        $this->assertSame(Result::SKIPPED, $this->admin->handle(['counting'])[0]->status);
        $this->assertSame(Result::DONE, $this->admin->handle(['counting'], true)[0]->status);
    }

    public function testResultsAreShownOnce(): void
    {
        $stored = [(new Result('counting', Result::DONE, '', [['message' => 'Run', 'type' => 'success']]))->toArray()];
        Functions\when('get_current_user_id')->justReturn(7);
        Functions\expect('get_transient')->twice()->with('coretik_migrations_results_7')->andReturn($stored, false);
        Functions\expect('delete_transient')->once()->with('coretik_migrations_results_7');

        $results = $this->admin->pullResults();

        $this->assertEquals([new Result('counting', Result::DONE, '', [['message' => 'Run', 'type' => 'success']])], $results);
        $this->assertSame([], $this->admin->pullResults());
    }

    public function testActionNamesFollowTheSlug(): void
    {
        $this->assertSame('coretik_migrations_run', $this->admin->action('run'));
        $this->assertSame('my_migrations_migrate', (new Admin($this->migrations, ['slug' => 'my-migrations']))->action('migrate'));
    }
}
