<?php

namespace Coretik\Services\Migrations;

/**
 * Outcome of one migration run
 */
final class Result
{
    public const DONE = 'done';
    public const FAILED = 'failed';
    /** Migration already done, not run again (without force) */
    public const SKIPPED = 'skipped';

    /**
     * @param array<int, array{message: string, type: string}> $logs messages of this run
     */
    public function __construct(
        public readonly string $key,
        public readonly string $status,
        public readonly string $message = '',
        public readonly array $logs = []
    ) {
    }

    public function succeeded(): bool
    {
        return self::DONE === $this->status;
    }

    public function failed(): bool
    {
        return self::FAILED === $this->status;
    }

    /**
     * @return array{key: string, status: string, message: string, logs: array<int, array{message: string, type: string}>}
     */
    public function toArray(): array
    {
        return ['key' => $this->key, 'status' => $this->status, 'message' => $this->message, 'logs' => $this->logs];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self((string)($data['key'] ?? ''), (string)($data['status'] ?? ''), (string)($data['message'] ?? ''), (array)($data['logs'] ?? []));
    }
}
