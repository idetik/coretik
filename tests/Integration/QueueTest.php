<?php

namespace Coretik\Tests\Integration;

use Coretik\Services\Queue\Admin;
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

    public function testListFiltersAndPaginatesNewestFirst(): void
    {
        $a = $this->queue->push(new CountingJob('a'), 0, 'g1');
        $b = $this->queue->push(new CountingJob('b'), HOUR_IN_SECONDS, 'g1');
        $c = $this->queue->push(new CountingJob('c'), 0, 'g2');

        $ids = fn (array $jobs) => array_map(fn (QueuedJob $job) => $job->id, $jobs);
        $this->assertSame([$c, $b, $a], $ids($this->store->list()));
        $this->assertSame([$b], $ids($this->store->list(['group' => 'g1'], 1)));
        $this->assertSame([$a], $ids($this->store->list(['group' => 'g1'], 1, 1)));
        $this->assertSame(2, $this->store->count(['group' => 'g1']));

        $this->queue->work();
        $this->assertSame([$c, $a], $ids($this->store->list(['status' => QueuedJob::DONE])));
        $this->assertSame(['default'], $this->store->queues());
    }

    public function testDeleteSkipsRunningJobs(): void
    {
        $running = $this->queue->push(new CountingJob('b'));
        $this->store->reserve(time());
        $pending = $this->queue->push(new CountingJob('a'));

        $this->assertTrue($this->queue->delete($pending));
        $this->assertFalse($this->queue->delete($running));
        $this->assertNull($this->store->find($pending));
        $this->assertNotNull($this->store->find($running));
    }

    public function testWorkRecordsItsLastRun(): void
    {
        delete_option(Queue::LAST_RUN_OPTION);
        $this->assertNull($this->queue->lastRun());

        $this->queue->work();

        $this->assertEqualsWithDelta(time(), $this->queue->lastRun(), 2);
    }

    public function testAdminPageListsTheJobs(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        $this->queue->push(new CountingJob('broken', 5), 0, 'camp-email-1-abc');
        $this->queue->push(new CountingJob('later'), HOUR_IN_SECONDS, 'other');
        foreach (range(1, 3) as $attempt) {
            global $wpdb;
            $wpdb->update($this->store->table(), ['available_at' => time()], ['job_group' => 'camp-email-1-abc']);
            $this->queue->work();
        }

        $_GET = ['page' => 'coretik-queue', 'group' => 'camp-email-1-abc'];
        ob_start();
        (new Admin($this->queue))->page();
        $html = (string)ob_get_clean();
        $_GET = [];

        $this->assertStringContainsString('CountingJob', $html);
        $this->assertStringContainsString('En échec', $html);
        $this->assertStringContainsString('RuntimeException: Not yet', $html);
        $this->assertStringContainsString('Relancer les échecs du groupe (1)', $html);
        $this->assertStringContainsString('&quot;name&quot;: &quot;broken&quot;', $html);
        $this->assertStringNotContainsString('&quot;name&quot;: &quot;later&quot;', $html, 'Other groups are filtered out');
    }

    public function testAdminPageWarnsWhenTheWorkerIsLate(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        update_option(Queue::LAST_RUN_OPTION, time() - HOUR_IN_SECONDS, false);
        $this->queue->push(new CountingJob('waiting'));

        ob_start();
        (new Admin($this->queue))->page();
        $html = (string)ob_get_clean();

        $this->assertStringContainsString('Le worker ne semble pas tourner', $html);
    }

    public function testAdminPageNeedsTheCapability(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'editor']));

        $this->expectException(\WPDieException::class);
        (new Admin($this->queue))->page();
    }
}
