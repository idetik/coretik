<?php

namespace Coretik\Services\Migrations;

/**
 * Statuses in one option (not autoloaded): [key => [status, ran_at, message]]
 */
class OptionStatusStore implements StatusStoreInterface
{
    public const OPTION = 'coretik_migrations';

    public function __construct(protected string $option = self::OPTION)
    {
    }

    public function get(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    public function all(): array
    {
        $statuses = \get_option($this->option, []);
        return \is_array($statuses) ? $statuses : [];
    }

    public function set(string $key, string $status, string $message = ''): void
    {
        $statuses = $this->all();
        $statuses[$key] = ['status' => $status, 'ran_at' => time(), 'message' => $message];
        $this->save($statuses);
    }

    public function forget(string $key): void
    {
        $statuses = $this->all();
        unset($statuses[$key]);
        $this->save($statuses);
    }

    /**
     * @param array<string, array{status: string, ran_at: int, message: string}> $statuses
     */
    protected function save(array $statuses): void
    {
        \update_option($this->option, $statuses, false);
    }
}
