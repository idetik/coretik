<?php

namespace Coretik\Services\Migrations;

/**
 * Last run of each migration
 */
interface StatusStoreInterface
{
    /**
     * @return array{status: string, ran_at: int, message: string}|null null when never run
     */
    public function get(string $key): ?array;

    /**
     * @return array<string, array{status: string, ran_at: int, message: string}>
     */
    public function all(): array;

    /**
     * @param string $status Result::DONE or Result::FAILED
     */
    public function set(string $key, string $status, string $message = ''): void;

    public function forget(string $key): void;
}
