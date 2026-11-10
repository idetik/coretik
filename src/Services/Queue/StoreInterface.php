<?php

namespace Coretik\Services\Queue;

/**
 * Storage of the queued jobs
 */
interface StoreInterface
{
    /**
     * @param array<string, mixed> $payload
     * @return int Job id
     */
    public function push(string $queue, string $job, array $payload, int $availableAt, ?string $group = null): int;

    /**
     * Claims the next available job (status running, one more attempt), null when there is none.
     * Atomic: two workers never get the same job.
     */
    public function reserve(int $now, ?string $queue = null): ?QueuedJob;

    public function find(int $id): ?QueuedJob;

    public function complete(int $id, int $now): void;

    /**
     * Back to pending, available again at $availableAt
     */
    public function release(int $id, int $availableAt, string $error): void;

    public function fail(int $id, int $now, string $error): void;

    /**
     * Jobs still running since before $reservedBefore (worker killed by a timeout, a fatal error…) back to pending
     *
     * @return int Number of jobs released
     */
    public function releaseStale(int $reservedBefore, int $now): int;

    /**
     * Failed jobs back to pending, attempts reset: every failed job, those of a group, or one job
     *
     * @return int Number of jobs retried
     */
    public function retry(int $now, ?string $group = null, ?int $id = null): int;

    /**
     * Number of jobs by status (every status is set), of a group or of every job
     *
     * @return array<string, int>
     */
    public function stats(?string $group = null): array;

    /**
     * Deletes done jobs finished before $doneBefore and failed jobs finished before $failedBefore
     *
     * @return int Number of jobs deleted
     */
    public function purge(int $doneBefore, int $failedBefore): int;
}
