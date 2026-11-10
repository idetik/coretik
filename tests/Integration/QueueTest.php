<?php

namespace Coretik\Tests\Integration;

use Coretik\Services\Queue\DatabaseStore;
use Coretik\Services\Queue\Queue;
use Coretik\Services\Queue\QueuedJob;
use Coretik\Tests\Integration\Fixtures\CountingJob;

/**
 * Queue on the table {prefix}coretik_jobs (created by tests/Integration/bootstrap.php)
 */
class QueueTest extends IntegrationTestCase
{
    private DatabaseStore $store;
    private Queue $queue;

    public function set_up(): void
    {
        parent::set_up();
        $this->store = new DatabaseStore();
        $this->queue = new Queue($this->store);
    }

    private function makeAvailable(int $id): void
    {
        global $wpdb;
        $wpdb->update($this->store->table(), ['available_at' => time()], ['id' => $id]);
    }

    public function testPushedJobIsStored(): void
    {
        $id = $this->queue->dispatch(new CountingJob('a'), 0, 'camp-1');

        $job = $this->store->find($id);
        $this->assertSame(QueuedJob::PENDING, $job->status);
        $this->assertSame('default', $job->queue);
        $this->assertSame(CountingJob::class, $job->job);
        $this->assertSame(['name' => 'a', 'failures' => 0], $job->payload);
        $this->assertSame('camp-1', $job->group);
        $this->assertSame(0, $job->attempts);
    }

    public function testWorkerRunsJobsOnce(): void
    {
        $first = $this->queue->push(new CountingJob('a'));
        $this->queue->push(new CountingJob('b'), HOUR_IN_SECONDS);

        $this->assertSame(1, $this->queue->work());
        $this->assertSame(0, $this->queue->work());

        $this->assertSame(1, (int)get_option('counted_a'));
        $this->assertFalse(get_option('counted_b'));
        $job = $this->store->find($first);
        $this->assertSame(QueuedJob::DONE, $job->status);
        $this->assertNotNull($job->finishedAt);
    }

    public function testReserveClaimsAJobOnce(): void
    {
        $id = $this->queue->push(new CountingJob('a'));

        $reserved = $this->store->reserve(time());
        $this->assertSame($id, $reserved->id);
        $this->assertSame(QueuedJob::RUNNING, $reserved->status);
        $this->assertSame(1, $reserved->attempts);
        $this->assertNull($this->store->reserve(time()), 'A running job is not reserved again');
    }

    public function testFailedAttemptIsRetried(): void
    {
        $id = $this->queue->push(new CountingJob('flaky', 1));

        $this->queue->work();
        $job = $this->store->find($id);
        $this->assertSame(QueuedJob::PENDING, $job->status);
        $this->assertGreaterThan(time(), $job->availableAt);
        $this->assertStringContainsString('Not yet', $job->error);

        $this->makeAvailable($id);
        $this->queue->work();

        $this->assertSame(QueuedJob::DONE, $this->store->find($id)->status);
        $this->assertSame(1, (int)get_option('counted_flaky'));
    }

    public function testFailedJobsAreRetriedByGroup(): void
    {
        $id = $this->queue->push(new CountingJob('broken', 3), 0, 'g');
        foreach (range(1, 3) as $attempt) {
            $this->makeAvailable($id);
            $this->queue->work();
        }
        $this->assertSame(['pending' => 0, 'running' => 0, 'done' => 0, 'failed' => 1], $this->queue->stats('g'));

        $this->assertSame(1, $this->queue->retry('g'));
        $this->queue->work();

        $this->assertSame(['pending' => 0, 'running' => 0, 'done' => 1, 'failed' => 0], $this->queue->stats('g'));
    }

    public function testStaleJobsAreReleased(): void
    {
        global $wpdb;
        $id = $this->queue->push(new CountingJob('killed'));
        $this->store->reserve(time());
        $wpdb->update($this->store->table(), ['reserved_at' => time() - HOUR_IN_SECONDS], ['id' => $id]);

        $this->assertSame(1, $this->store->releaseStale(time() - 600, time()));
        $this->queue->work();

        $job = $this->store->find($id);
        $this->assertSame(QueuedJob::DONE, $job->status);
        $this->assertSame(2, $job->attempts);
    }

    public function testPurgeDeletesOldJobs(): void
    {
        global $wpdb;
        $old = $this->queue->push(new CountingJob('old'));
        $recent = $this->queue->push(new CountingJob('recent'));
        $this->queue->work();
        $wpdb->update($this->store->table(), ['finished_at' => time() - 8 * DAY_IN_SECONDS], ['id' => $old]);

        $this->assertSame(1, $this->store->purge(time() - 7 * DAY_IN_SECONDS, time() - 30 * DAY_IN_SECONDS));
        $this->assertNull($this->store->find($old));
        $this->assertNotNull($this->store->find($recent));
    }

    public function testListenSchedulesTheWorker(): void
    {
        wp_clear_scheduled_hook(Queue::HOOK);
        $this->queue->listen();

        $this->assertNotFalse(wp_next_scheduled(Queue::HOOK));
        $this->assertSame(MINUTE_IN_SECONDS, wp_get_schedules()[Queue::SCHEDULE]['interval']);
    }
}
