<?php

namespace Coretik\Services\Queue;

/**
 * A job stored in the queue (timestamps in seconds, UTC)
 */
final class QueuedJob
{
    public const PENDING = 'pending';
    public const RUNNING = 'running';
    public const DONE = 'done';
    public const FAILED = 'failed';

    public const STATUSES = [self::PENDING, self::RUNNING, self::DONE, self::FAILED];

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly int $id,
        public readonly string $queue,
        public readonly string $job,
        public readonly array $payload,
        public readonly string $status,
        public readonly int $attempts,
        public readonly int $availableAt,
        public readonly int $createdAt,
        public readonly ?string $group = null,
        public readonly ?int $reservedAt = null,
        public readonly ?int $finishedAt = null,
        public readonly ?string $error = null
    ) {
    }
}
