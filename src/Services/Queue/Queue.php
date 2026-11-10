<?php

namespace Coretik\Services\Queue;

/**
 * Background jobs: stored by the StoreInterface, run by WP-Cron every minute.
 *
 * $container['queue'] = fn () => new Queue(new DatabaseStore());  // App::init() calls listen()
 *
 * coretik()->queue()->dispatch(new SendNewsletter($userId));       // ShouldQueue: queued, otherwise run now
 * coretik()->queue()->push(new SendNewsletter($userId), delay: 600, group: 'newsletter-42');
 * coretik()->queue()->stats('newsletter-42');                      // ['pending' => 12, 'running' => 1, 'done' => 30, 'failed' => 0]
 *
 * - A failed attempt (exception) is retried after backoff(), until tries(); then the job is failed and failed() is called.
 * - A job still running after timeout() seconds (worker killed) is released and counts one attempt.
 * - Done jobs are kept 7 days, failed jobs 30 days (keepDone(), keepFailed()).
 * - sync(true) (or the filter coretik/queue/sync): jobs run when pushed, exceptions are thrown to the caller.
 *
 * WP-Cron runs on page visits: use a system cron (wp-cron.php or `wp cron event run --due-now` every minute) so jobs
 * run on time, or `wp coretik queue work`.
 *
 * Hooks: coretik/queue/pushed, coretik/queue/processing, coretik/queue/done, coretik/queue/retrying,
 * coretik/queue/failed (QueuedJob, JobInterface|null, Throwable for the last two)
 */
class Queue
{
    public const DEFAULT_QUEUE = 'default';
    public const HOOK = 'coretik/queue/work';
    public const SCHEDULE = 'coretik_queue';

    protected int $limit = 100;
    protected int $timeBudget = 45;
    protected int $timeout = 600;
    protected int $keepDone = 7 * DAY_IN_SECONDS;
    protected int $keepFailed = 30 * DAY_IN_SECONDS;
    protected ?bool $sync = null;

    public function __construct(protected StoreInterface $store)
    {
    }

    public function store(): StoreInterface
    {
        return $this->store;
    }

    /**
     * Maximum number of jobs per worker run (provider rate limits…)
     */
    public function limit(int $limit): self
    {
        $this->limit = max(1, $limit);
        return $this;
    }

    /**
     * Seconds after which a worker run stops taking jobs (it lets the running job end)
     */
    public function timeBudget(int $seconds): self
    {
        $this->timeBudget = max(1, $seconds);
        return $this;
    }

    /**
     * Seconds after which a running job is considered stopped (worker killed)
     */
    public function timeout(int $seconds): self
    {
        $this->timeout = max(60, $seconds);
        return $this;
    }

    public function keepDone(int $seconds): self
    {
        $this->keepDone = max(0, $seconds);
        return $this;
    }

    public function keepFailed(int $seconds): self
    {
        $this->keepFailed = max(0, $seconds);
        return $this;
    }

    /**
     * Run the jobs when they are pushed (development without cron), null to use the filter coretik/queue/sync
     */
    public function sync(?bool $sync = true): self
    {
        $this->sync = $sync;
        return $this;
    }

    public function isSync(): bool
    {
        return (bool)\apply_filters('coretik/queue/sync', $this->sync ?? false);
    }

    /**
     * WP-Cron worker, every minute
     */
    public function listen(): void
    {
        \add_filter('cron_schedules', [$this, 'schedules']);
        \add_action(static::HOOK, [$this, 'cron']);
        \did_action('init') ? $this->schedule() : \add_action('init', [$this, 'schedule']);

        if (defined('WP_CLI') && \WP_CLI && class_exists('WP_CLI')) {
            \WP_CLI::add_command('coretik queue', new Cli($this));
        }
    }

    /**
     * @param array<string, array{interval: int, display: string}> $schedules
     * @return array<string, array{interval: int, display: string}>
     */
    public function schedules(array $schedules): array
    {
        $schedules[static::SCHEDULE] ??= ['interval' => MINUTE_IN_SECONDS, 'display' => 'Coretik queue (every minute)'];
        return $schedules;
    }

    public function schedule(): void
    {
        if (!\wp_next_scheduled(static::HOOK)) {
            \wp_schedule_event(time(), static::SCHEDULE, static::HOOK);
        }
    }

    public function cron(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(max($this->timeBudget, $this->timeout) + 30);
        }

        $this->work();
    }

    /**
     * Queued when the job implements ShouldQueue, run now otherwise
     *
     * @return int|null Job id, null when run now
     */
    public function dispatch(JobInterface $job, int $delay = 0, ?string $group = null): ?int
    {
        if (!$job instanceof ShouldQueue) {
            $job->handle();
            return null;
        }

        return $this->push($job, $delay, $group);
    }

    /**
     * Queues the job, available in $delay seconds. A group gathers jobs to follow them: stats($group), retry($group)
     *
     * @return int|null Job id, null in sync mode (run now)
     */
    public function push(JobInterface $job, int $delay = 0, ?string $group = null): ?int
    {
        if ($this->isSync()) {
            $job->handle();
            return null;
        }

        $id = $this->store->push($job->queue(), $job::class, $job->toPayload(), time() + max(0, $delay), $group);
        \do_action('coretik/queue/pushed', $id, $job, $group);

        return $id;
    }

    /**
     * Runs the available jobs, until the limit or the time budget
     *
     * @return int Number of jobs run (done or not)
     */
    public function work(?string $queue = null, ?int $limit = null, ?int $timeBudget = null): int
    {
        $limit ??= $this->limit;
        $timeBudget ??= $this->timeBudget;
        $start = microtime(true);
        $count = 0;

        $this->store->releaseStale(time() - $this->timeout, time());

        while ($count < $limit && microtime(true) - $start < $timeBudget) {
            $record = $this->store->reserve(time(), $queue);
            if (!$record) {
                break;
            }

            $this->run($record);
            $count++;
        }

        $this->store->purge(time() - $this->keepDone, time() - $this->keepFailed);

        return $count;
    }

    /**
     * Runs a reserved job
     *
     * @return bool Done
     */
    public function run(QueuedJob $record): bool
    {
        try {
            $job = $this->rebuild($record);
        } catch (\Throwable $e) {
            $this->fail($record, null, $e);
            return false;
        }

        // Released by releaseStale() after its last attempt: it already had its chances
        if ($record->attempts > $job->tries()) {
            $this->fail($record, $job, new \RuntimeException($record->error ?: sprintf('Too many attempts (%d).', $record->attempts)));
            return false;
        }

        try {
            \do_action('coretik/queue/processing', $record, $job);
            $job->handle();
        } catch (\Throwable $e) {
            if ($record->attempts < $job->tries()) {
                $this->store->release($record->id, time() + max(0, $job->backoff($record->attempts)), static::message($e));
                \do_action('coretik/queue/retrying', $record, $job, $e);
            } else {
                $this->fail($record, $job, $e);
            }
            return false;
        }

        $this->store->complete($record->id, time());
        \do_action('coretik/queue/done', $record, $job);

        return true;
    }

    /**
     * Failed jobs run again (attempts reset): every failed job, those of a group, or one job
     */
    public function retry(?string $group = null, ?int $id = null): int
    {
        return $this->store->retry(time(), $group, $id);
    }

    /**
     * @return array<string, int> Number of jobs by status
     */
    public function stats(?string $group = null): array
    {
        return $this->store->stats($group);
    }

    protected function rebuild(QueuedJob $record): JobInterface
    {
        $class = $record->job;
        if (!class_exists($class) || !is_subclass_of($class, JobInterface::class)) {
            throw new \UnexpectedValueException(sprintf('Unknown job class "%s".', $class));
        }

        return $class::fromPayload($record->payload);
    }

    protected function fail(QueuedJob $record, ?JobInterface $job, \Throwable $exception): void
    {
        $this->store->fail($record->id, time(), static::message($exception));

        if ($job) {
            try {
                $job->failed($exception);
            } catch (\Throwable $e) {
                \error_log(sprintf('[coretik/queue] job %d: failed() threw %s', $record->id, static::message($e)));
            }
        }

        \do_action('coretik/queue/failed', $record, $job, $exception);
    }

    protected static function message(\Throwable $e): string
    {
        return sprintf('%s: %s', $e::class, $e->getMessage());
    }
}
