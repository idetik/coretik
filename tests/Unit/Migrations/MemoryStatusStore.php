<?php

namespace Coretik\Tests\Unit\Migrations;

use Coretik\Services\Migrations\StatusStoreInterface;

class MemoryStatusStore implements StatusStoreInterface
{
    /** @var array<string, array{status: string, ran_at: int, message: string}> */
    public array $statuses = [];

    public function get(string $key): ?array
    {
        return $this->statuses[$key] ?? null;
    }

    public function all(): array
    {
        return $this->statuses;
    }

    public function set(string $key, string $status, string $message = ''): void
    {
        $this->statuses[$key] = ['status' => $status, 'ran_at' => time(), 'message' => $message];
    }

    public function forget(string $key): void
    {
        unset($this->statuses[$key]);
    }
}
