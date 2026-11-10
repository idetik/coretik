<?php

namespace Coretik\Services\Queue;

/**
 * A unit of work run by the Queue: queued when it implements ShouldQueue (Queue::dispatch()), or always with
 * Queue::push(). Stored as its class and a JSON payload, rebuilt in the worker with fromPayload(): keep ids and
 * scalars in the payload, never objects (models are loaded again when the job runs).
 */
interface JobInterface
{
    /**
     * The work: an exception makes the attempt fail (retried until tries(), then failed())
     */
    public function handle(): void;

    /**
     * JSON-serializable data to rebuild the job
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array;

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): static;

    /**
     * Queue name, to run queues separately: Queue::work('mail')
     */
    public function queue(): string;

    /**
     * Maximum number of attempts
     */
    public function tries(): int;

    /**
     * Seconds before the next attempt, after $attempts failed attempts
     */
    public function backoff(int $attempts): int;

    /**
     * Last attempt failed: log, notify…
     */
    public function failed(\Throwable $exception): void;
}
