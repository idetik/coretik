<?php

namespace Coretik\Tests\Unit\Queue;

use Coretik\Services\Queue\QueuedJob;
use Coretik\Services\Queue\StoreInterface;

/**
 * In-memory store for the Queue unit tests
 */
class MemoryStore implements StoreInterface
{
    /** @var array<int, QueuedJob> */
    public array $jobs = [];

    private int $next = 1;

    public function push(string $queue, string $job, array $payload, int $availableAt, ?string $group = null): int
    {
        $id = $this->next++;
        $this->jobs[$id] = new QueuedJob($id, $queue, $job, json_decode((string)json_encode($payload), true), QueuedJob::PENDING, 0, $availableAt, time(), $group);
        return $id;
    }

    public function reserve(int $now, ?string $queue = null): ?QueuedJob
    {
        $candidates = array_filter(
            $this->jobs,
            fn (QueuedJob $job) => QueuedJob::PENDING === $job->status && $job->availableAt <= $now && (null === $queue || $queue === $job->queue)
        );
        usort($candidates, fn (QueuedJob $a, QueuedJob $b) => [$a->availableAt, $a->id] <=> [$b->availableAt, $b->id]);
        $job = $candidates[0] ?? null;
        if (!$job) {
            return null;
        }

        return $this->set($job->id, ['status' => QueuedJob::RUNNING, 'reservedAt' => $now, 'attempts' => $job->attempts + 1]);
    }

    public function find(int $id): ?QueuedJob
    {
        return $this->jobs[$id] ?? null;
    }

    public function complete(int $id, int $now): void
    {
        $this->set($id, ['status' => QueuedJob::DONE, 'finishedAt' => $now, 'reservedAt' => null, 'error' => null]);
    }

    public function release(int $id, int $availableAt, string $error): void
    {
        $this->set($id, ['status' => QueuedJob::PENDING, 'availableAt' => $availableAt, 'reservedAt' => null, 'error' => $error]);
    }

    public function fail(int $id, int $now, string $error): void
    {
        $this->set($id, ['status' => QueuedJob::FAILED, 'finishedAt' => $now, 'reservedAt' => null, 'error' => $error]);
    }

    public function releaseStale(int $reservedBefore, int $now): int
    {
        $count = 0;
        foreach ($this->jobs as $job) {
            if (QueuedJob::RUNNING === $job->status && $job->reservedAt < $reservedBefore) {
                $this->set($job->id, ['status' => QueuedJob::PENDING, 'reservedAt' => null, 'availableAt' => $now, 'error' => 'stale']);
                $count++;
            }
        }
        return $count;
    }

    public function retry(int $now, ?string $group = null, ?int $id = null): int
    {
        $count = 0;
        foreach ($this->jobs as $job) {
            if (QueuedJob::FAILED === $job->status && (null === $group || $group === $job->group) && (null === $id || $id === $job->id)) {
                $this->set($job->id, ['status' => QueuedJob::PENDING, 'attempts' => 0, 'availableAt' => $now, 'finishedAt' => null, 'error' => null]);
                $count++;
            }
        }
        return $count;
    }

    public function stats(?string $group = null): array
    {
        $stats = array_fill_keys(QueuedJob::STATUSES, 0);
        foreach ($this->jobs as $job) {
            if (null === $group || $group === $job->group) {
                $stats[$job->status]++;
            }
        }
        return $stats;
    }

    public function purge(int $doneBefore, int $failedBefore): int
    {
        $before = count($this->jobs);
        $this->jobs = array_filter(
            $this->jobs,
            fn (QueuedJob $job) => !(QueuedJob::DONE === $job->status && $job->finishedAt < $doneBefore)
                && !(QueuedJob::FAILED === $job->status && $job->finishedAt < $failedBefore)
        );
        return $before - count($this->jobs);
    }

    public function list(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $jobs = array_values(array_filter($this->jobs, fn (QueuedJob $job) => $this->matches($job, $filters)));
        usort($jobs, fn (QueuedJob $a, QueuedJob $b) => $b->id <=> $a->id);
        return array_slice($jobs, $offset, $limit);
    }

    public function count(array $filters = []): int
    {
        return count(array_filter($this->jobs, fn (QueuedJob $job) => $this->matches($job, $filters)));
    }

    public function queues(): array
    {
        $queues = array_values(array_unique(array_map(fn (QueuedJob $job) => $job->queue, $this->jobs)));
        sort($queues);
        return $queues;
    }

    public function delete(int $id): bool
    {
        if (!isset($this->jobs[$id]) || QueuedJob::RUNNING === $this->jobs[$id]->status) {
            return false;
        }
        unset($this->jobs[$id]);
        return true;
    }

    private function matches(QueuedJob $job, array $filters): bool
    {
        return (empty($filters['status']) || $filters['status'] === $job->status)
            && (empty($filters['queue']) || $filters['queue'] === $job->queue)
            && (empty($filters['group']) || $filters['group'] === $job->group);
    }

    /**
     * Test helper: change a stored job
     *
     * @param array<string, mixed> $changes
     */
    public function set(int $id, array $changes): QueuedJob
    {
        $job = $this->jobs[$id];
        $values = get_object_vars($job);
        foreach ($changes as $key => $value) {
            $values[$key] = $value;
        }
        return $this->jobs[$id] = new QueuedJob(...$values);
    }
}
