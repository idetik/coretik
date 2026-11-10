<?php

namespace Coretik\Services\Queue;

/**
 * wp coretik queue work|stats|retry
 */
class Cli
{
    public function __construct(protected Queue $queue)
    {
    }

    /**
     * Runs the available jobs.
     *
     * ## OPTIONS
     *
     * [--queue=<queue>]
     * : Only the jobs of this queue.
     *
     * [--limit=<limit>]
     * : Maximum number of jobs.
     *
     * [--time=<seconds>]
     * : Time budget in seconds.
     *
     * @param string[] $args
     * @param array<string, string> $assoc
     */
    public function work(array $args, array $assoc): void
    {
        $count = $this->queue->work(
            $assoc['queue'] ?? null,
            isset($assoc['limit']) ? (int)$assoc['limit'] : null,
            isset($assoc['time']) ? (int)$assoc['time'] : null
        );

        \WP_CLI::success(sprintf('%d job(s) run.', $count));
    }

    /**
     * Number of jobs by status.
     *
     * ## OPTIONS
     *
     * [--group=<group>]
     * : Only the jobs of this group.
     *
     * @param string[] $args
     * @param array<string, string> $assoc
     */
    public function stats(array $args, array $assoc): void
    {
        foreach ($this->queue->stats($assoc['group'] ?? null) as $status => $count) {
            \WP_CLI::line(sprintf('%-8s %d', $status, $count));
        }
    }

    /**
     * Runs the failed jobs again.
     *
     * ## OPTIONS
     *
     * [<id>]
     * : One job.
     *
     * [--group=<group>]
     * : Only the jobs of this group.
     *
     * @param string[] $args
     * @param array<string, string> $assoc
     */
    public function retry(array $args, array $assoc): void
    {
        $count = $this->queue->retry($assoc['group'] ?? null, isset($args[0]) ? (int)$args[0] : null);
        \WP_CLI::success(sprintf('%d job(s) queued again.', $count));
    }
}
