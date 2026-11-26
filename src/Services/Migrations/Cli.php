<?php

namespace Coretik\Services\Migrations;

/**
 * wp app migrate [<key>...] [--force] [--list] [--mark-done]
 *
 * - Without key: runs the pending migrations (seeders excluded).
 * - With keys: runs them in this order (seeders included); a migration already done is skipped, unless --force.
 * - --list: migrations and seeders with the status of their last run.
 * - --mark-done: records the migrations (the given keys, or all the pending ones) as done without running them.
 *
 * Stops at the first failure, with a non zero exit code (deployment scripts).
 */
class Cli
{
    public function __construct(protected Migrations $migrations)
    {
    }

    /**
     * Arguments of WP_CLI::add_command()
     *
     * @return array<string, mixed>
     */
    public static function definition(): array
    {
        return [
            'shortdesc' => 'Runs the migrations and seeders of the application.',
            'longdesc' => '## EXAMPLES' . "\n\n"
                . '    # Pending migrations' . "\n" . '    $ wp app migrate' . "\n\n"
                . '    # Some migrations or seeders' . "\n" . '    $ wp app migrate initial-seeder' . "\n\n"
                . '    # Status' . "\n" . '    $ wp app migrate --list',
            'synopsis' => [
                ['type' => 'positional', 'name' => 'key', 'optional' => true, 'repeating' => true, 'description' => 'Migrations or seeders to run, the pending migrations without key.'],
                ['type' => 'flag', 'name' => 'force', 'optional' => true, 'description' => 'Runs again the migrations already done.'],
                ['type' => 'flag', 'name' => 'list', 'optional' => true, 'description' => 'Lists the migrations and seeders with their status.'],
                ['type' => 'flag', 'name' => 'mark-done', 'optional' => true, 'description' => 'Records the migrations as done without running them.'],
            ],
        ];
    }

    /**
     * @param string[] $args
     * @param array<string, mixed> $assoc
     */
    public function __invoke(array $args, array $assoc = []): void
    {
        if (!empty($assoc['list'])) {
            $this->list();
            return;
        }

        foreach ($args as $key) {
            if (!$this->migrations->has($key)) {
                \WP_CLI::error(sprintf('Unknown migration "%s" (%s)', $key, implode(', ', $this->migrations->keys())));
                return;
            }
        }

        if (!empty($assoc['mark-done'])) {
            $keys = $args ?: $this->migrations->pending();
            $this->migrations->markDone($keys);
            \WP_CLI::success(sprintf('%d migration(s) marked as done%s', count($keys), $keys ? ': ' . implode(', ', $keys) : '.'));
            return;
        }

        $keys = $args ?: $this->migrations->pending();
        if (empty($keys)) {
            \WP_CLI::success('Nothing to migrate.');
            return;
        }

        foreach ($keys as $key) {
            $result = $this->migrations->run($key, !empty($assoc['force']));
            $this->print($result->logs);

            switch ($result->status) {
                case Result::SKIPPED:
                    \WP_CLI::warning(sprintf('Migration %s already done, skipped (--force to run it again)', $key));
                    break;
                case Result::FAILED:
                    \WP_CLI::error(sprintf('Migration %s failed: %s', $key, $this->plain($result->message)));
                    return;
                default:
                    \WP_CLI::success(sprintf('Migration %s', $key));
            }
        }
    }

    protected function list(): void
    {
        \WP_CLI\Utils\format_items('table', static::rows($this->migrations), ['key', 'type', 'status', 'ran_at', 'message', 'description']);
    }

    /**
     * Lines of --list
     *
     * @return array<int, array{key: string, type: string, status: string, ran_at: string, message: string, description: string}>
     */
    public static function rows(Migrations $migrations): array
    {
        $rows = [];
        foreach ($migrations->keys() as $key) {
            $status = $migrations->status($key);
            $seeder = $migrations->isSeeder($key);
            $rows[] = [
                'key' => $key,
                'type' => $seeder ? 'seeder' : 'migration',
                'status' => $status['status'] ?? ($seeder ? '' : 'pending'),
                'ran_at' => $status ? \wp_date('Y-m-d H:i:s', (int)$status['ran_at']) : '',
                'message' => (string)($status['message'] ?? ''),
                'description' => $migrations->description($key),
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, array{message: string, type: string}> $logs
     */
    protected function print(array $logs): void
    {
        foreach ($logs as $log) {
            $message = $this->plain($log['message'] ?? '');
            \in_array($log['type'] ?? '', ['error', 'warning'], true) ? \WP_CLI::warning($message) : \WP_CLI::log($message);
        }
    }

    /**
     * Messages are written for the back-office: no tags, no entities
     */
    protected function plain(string $message): string
    {
        return \html_entity_decode(\wp_strip_all_tags($message), ENT_QUOTES, 'UTF-8');
    }
}
