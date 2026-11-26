<?php

namespace Coretik\Services\Migrations;

/**
 * Registry of the migrations and seeders of the application, with the status of their last run.
 *
 * $container['migrations'] = fn () => (new Migrations())  // App::init() calls listen()
 *     ->add(SplitAddress::class, InitialSeeder::class); // Class names are built on first use
 *
 * coretik()->migrations()->migrate();                 // Migrations never done (seeders excluded)
 * coretik()->migrations()->run('initial-seeder');     // One migration or seeder
 * coretik()->migrations()->pending();                 // ['split-address']
 *
 * - A migration runs once: run() skips it once done, unless forced. A seeder runs each time it is asked.
 * - A MigrationErrorException fails the migration with its message; other exceptions are recorded then thrown.
 * - WP-CLI: wp app migrate [<key>...] [--force] [--list] [--mark-done] (cli(), cli(false) to disable it).
 * - Back-office page Coretik > Migrations (admin(), admin(false) to disable it).
 *
 * Hooks: coretik/migrations/running (MigrationInterface), coretik/migrations/done (MigrationInterface, Result),
 * coretik/migrations/failed (MigrationInterface, Result, Throwable)
 */
class Migrations
{
    public const DEFAULT_COMMAND = 'app migrate';

    /** @var array<int, MigrationInterface|class-string<MigrationInterface>> Added, not built yet */
    protected array $queued = [];

    /** @var array<string, MigrationInterface> */
    protected array $migrations = [];

    protected StatusStoreInterface $store;

    /** @var array<string, mixed>|false Options of the back-office page (Admin::DEFAULTS), false without page */
    protected array|false $admin = [];

    /** WP-CLI command, false without command */
    protected string|false $command = self::DEFAULT_COMMAND;

    public function __construct(?StatusStoreInterface $store = null)
    {
        $this->store = $store ?? new OptionStatusStore();
    }

    public function store(): StatusStoreInterface
    {
        return $this->store;
    }

    /**
     * Migrations or seeders, instances or class names (built without argument, on first use). Order of the runs.
     *
     * @param MigrationInterface|class-string<MigrationInterface> ...$migrations
     */
    public function add(MigrationInterface|string ...$migrations): static
    {
        array_push($this->queued, ...$migrations);
        return $this;
    }

    /**
     * Back-office page (Admin::DEFAULTS: slug, title, capability, parent…), false to disable it
     *
     * @param array<string, mixed>|false $options
     */
    public function admin(array|false $options): static
    {
        $this->admin = $options;
        return $this;
    }

    /**
     * WP-CLI command name ("app migrate" by default), false to disable it
     */
    public function cli(string|false $command): static
    {
        $this->command = $command;
        return $this;
    }

    public function listen(): void
    {
        if (false !== $this->admin && \is_admin()) {
            (new Admin($this, $this->admin))->hooks();
        }

        if (false !== $this->command && defined('WP_CLI') && \WP_CLI && class_exists('WP_CLI')) {
            \WP_CLI::add_command($this->command, new Cli($this), Cli::definition());
        }
    }

    /**
     * @return array<string, MigrationInterface> by key, in the order they were added
     */
    public function migrations(): array
    {
        while ($this->queued) {
            $migration = array_shift($this->queued);
            if (\is_string($migration)) {
                $migration = new $migration();
            }

            if (!$migration instanceof MigrationInterface) {
                throw new \InvalidArgumentException(sprintf('%s does not implement %s', get_class($migration), MigrationInterface::class));
            }

            $key = $migration->getKey();
            if ('' === $key) {
                throw new \InvalidArgumentException(sprintf('Migration %s has no key (MIGRATION_KEY)', get_class($migration)));
            }

            if (isset($this->migrations[$key]) && $this->migrations[$key] !== $migration) {
                throw new \InvalidArgumentException(sprintf('Migration key "%s" is already used by %s', $key, get_class($this->migrations[$key])));
            }

            $this->migrations[$key] = $migration;
        }

        return $this->migrations;
    }

    /**
     * @return string[]
     */
    public function keys(): array
    {
        return array_keys($this->migrations());
    }

    public function has(string $key): bool
    {
        return isset($this->migrations()[$key]);
    }

    /**
     * @throws \InvalidArgumentException unknown key
     */
    public function get(string $key): MigrationInterface
    {
        if (!$this->has($key)) {
            throw new \InvalidArgumentException(sprintf('Unknown migration "%s" (%s)', $key, implode(', ', $this->keys())));
        }

        return $this->migrations()[$key];
    }

    public function isSeeder(string $key): bool
    {
        return $this->get($key) instanceof SeederInterface;
    }

    /**
     * Last run: status (Result::DONE, Result::FAILED), ran_at (timestamp), message. Null when never run.
     *
     * @return array{status: string, ran_at: int, message: string}|null
     */
    public function status(string $key): ?array
    {
        return $this->store->get($key);
    }

    public function isDone(string $key): bool
    {
        return Result::DONE === ($this->status($key)['status'] ?? null);
    }

    /**
     * Migrations never done (failed ones included), seeders excluded
     *
     * @return string[]
     */
    public function pending(): array
    {
        return array_values(array_filter($this->keys(), fn (string $key) => !$this->isSeeder($key) && !$this->isDone($key)));
    }

    /**
     * Runs the pending migrations, stops at the first failure
     *
     * @return Result[]
     */
    public function migrate(): array
    {
        return $this->runMany($this->pending());
    }

    /**
     * Runs migrations by key, in the given order; stops at the first failure. Unknown keys throw before any run.
     *
     * @param string[] $keys
     * @return Result[]
     */
    public function runMany(array $keys, bool $force = false): array
    {
        $keys = array_values(array_unique($keys));
        array_map([$this, 'get'], $keys);

        $results = [];
        foreach ($keys as $key) {
            $results[] = $result = $this->run($key, $force);
            if ($result->failed()) {
                break;
            }
        }

        return $results;
    }

    /**
     * Runs one migration or seeder. A migration already done is skipped, unless forced.
     *
     * @throws \InvalidArgumentException unknown key
     * @throws \Throwable unexpected error of the migration (recorded as failed first)
     */
    public function run(string $key, bool $force = false): Result
    {
        $migration = $this->get($key);

        if (!$force && !$migration instanceof SeederInterface && $this->isDone($key)) {
            return new Result($key, Result::SKIPPED, 'Already done');
        }

        $logged = count($migration->getLogs());
        \do_action('coretik/migrations/running', $migration);

        try {
            $migration->migrate();
        } catch (MigrationErrorException $exception) {
            return $this->failed($migration, $exception, $exception->getMessage(), $logged);
        } catch (\Throwable $exception) {
            $this->failed($migration, $exception, sprintf('%s: %s', get_class($exception), $exception->getMessage()), $logged);
            throw $exception;
        }

        $this->store->set($key, Result::DONE);
        $result = new Result($key, Result::DONE, '', array_slice($migration->getLogs(), $logged));
        \do_action('coretik/migrations/done', $migration, $result);

        return $result;
    }

    /**
     * Records migrations as done without running them: migrations already applied before their registration
     * (adoption of this service on an existing site)
     *
     * @param string[] $keys
     */
    public function markDone(array $keys): void
    {
        array_map([$this, 'get'], $keys);
        foreach ($keys as $key) {
            $this->store->set($key, Result::DONE, 'Marked as done');
        }
    }

    /**
     * Forgets the status of a migration: pending again
     */
    public function reset(string $key): void
    {
        $this->store->forget($key);
    }

    protected function failed(MigrationInterface $migration, \Throwable $exception, string $message, int $logged): Result
    {
        $this->store->set($migration->getKey(), Result::FAILED, $message);
        $result = new Result($migration->getKey(), Result::FAILED, $message, array_slice($migration->getLogs(), $logged));
        \do_action('coretik/migrations/failed', $migration, $result, $exception);

        return $result;
    }

    /**
     * Description of a migration (Migration::getDescription()), empty without
     */
    public function description(string $key): string
    {
        $migration = $this->get($key);
        return method_exists($migration, 'getDescription') ? (string)$migration->getDescription() : '';
    }
}
