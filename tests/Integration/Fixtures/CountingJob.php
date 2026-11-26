<?php

namespace Coretik\Tests\Integration\Fixtures;

use Coretik\Services\Queue\Job;
use Coretik\Services\Queue\ShouldQueue;

/**
 * Increments the option "counted_{name}", fails the first $failures attempts
 */
class CountingJob extends Job implements ShouldQueue
{
    public function __construct(public string $name, public int $failures = 0)
    {
    }

    public function handle(): void
    {
        $attempts = (int)get_option('attempts_' . $this->name) + 1;
        update_option('attempts_' . $this->name, $attempts);
        if ($attempts <= $this->failures) {
            throw new \RuntimeException('Not yet');
        }
        update_option('counted_' . $this->name, (int)get_option('counted_' . $this->name) + 1);
    }

    public function toPayload(): array
    {
        return ['name' => $this->name, 'failures' => $this->failures];
    }

    public static function fromPayload(array $payload): static
    {
        return new static((string)$payload['name'], (int)$payload['failures']);
    }
}
