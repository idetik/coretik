<?php

namespace Coretik\Services\Queue;

/**
 * Job with the default settings: queue "default", 3 attempts, 1 then 2 minutes between them.
 * Implement ShouldQueue to queue it with Queue::dispatch().
 *
 * class SendNewsletter extends Job implements ShouldQueue
 * {
 *     public function __construct(protected int $userId) {}
 *
 *     public function handle(): void { … }
 *     public function toPayload(): array { return ['user' => $this->userId]; }
 *     public static function fromPayload(array $payload): static { return new static((int)$payload['user']); }
 * }
 *
 * @phpstan-consistent-constructor
 */
abstract class Job implements JobInterface
{
    public function queue(): string
    {
        return Queue::DEFAULT_QUEUE;
    }

    public function tries(): int
    {
        return 3;
    }

    public function backoff(int $attempts): int
    {
        return MINUTE_IN_SECONDS * max(1, $attempts);
    }

    public function failed(\Throwable $exception): void
    {
    }
}
