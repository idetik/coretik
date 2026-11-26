<?php

namespace Coretik\Tests\Unit\Queue;

use Coretik\Services\Queue\Job;

/**
 * Job recording its runs: fails the first $failures attempts of each name
 */
abstract class TestJob extends Job
{
    /** @var string[] */
    public static array $handled = [];

    /** @var array<string, int> */
    public static array $attempts = [];

    /** @var array<string, string> */
    public static array $failed = [];

    public function __construct(public string $name, public int $failures = 0, public int $maxTries = 3, public string $queueName = 'default')
    {
    }

    public static function reset(): void
    {
        static::$handled = [];
        static::$attempts = [];
        static::$failed = [];
    }

    public function handle(): void
    {
        static::$attempts[$this->name] = (static::$attempts[$this->name] ?? 0) + 1;
        if (static::$attempts[$this->name] <= $this->failures) {
            throw new \RuntimeException('Attempt ' . static::$attempts[$this->name] . ' of ' . $this->name);
        }
        static::$handled[] = $this->name;
    }

    public function toPayload(): array
    {
        return ['name' => $this->name, 'failures' => $this->failures, 'tries' => $this->maxTries, 'queue' => $this->queueName];
    }

    public static function fromPayload(array $payload): static
    {
        return new static((string)$payload['name'], (int)$payload['failures'], (int)$payload['tries'], (string)$payload['queue']);
    }

    public function queue(): string
    {
        return $this->queueName;
    }

    public function tries(): int
    {
        return $this->maxTries;
    }

    public function failed(\Throwable $exception): void
    {
        static::$failed[$this->name] = $exception->getMessage();
    }
}
