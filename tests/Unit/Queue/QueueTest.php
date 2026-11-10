<?php

namespace Coretik\Tests\Unit\Queue;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Coretik\Services\Queue\Queue;
use Coretik\Services\Queue\QueuedJob;
use Coretik\Tests\TestCase;

class QueueTest extends TestCase
{
    private MemoryStore $store;
    private Queue $queue;

    protected function setUp(): void
    {
        parent::setUp();
        TestJob::reset();
        $this->store = new MemoryStore();
        $this->queue = new Queue($this->store);
    }

    /**
     * Failed attempts are released later: make them available now
     */
    private function availableNow(): void
    {
        foreach ($this->store->jobs as $job) {
            if (QueuedJob::PENDING === $job->status) {
                $this->store->set($job->id, ['availableAt' => time()]);
            }
        }
    }

    public function testDispatchQueuesShouldQueueJobs(): void
    {
        Actions\expectDone('coretik/queue/pushed')->once();

        $id = $this->queue->dispatch(new RecordingJob('a'), 0, 'group-1');

        $this->assertIsInt($id);
        $this->assertSame([], TestJob::$handled);
        $job = $this->store->find($id);
        $this->assertSame(RecordingJob::class, $job->job);
        $this->assertSame('group-1', $job->group);
        $this->assertSame(['name' => 'a', 'failures' => 0, 'tries' => 3, 'queue' => 'default'], $job->payload);
    }

    public function testDispatchRunsOtherJobsNow(): void
    {
        $this->assertNull($this->queue->dispatch(new ImmediateJob('now')));
        $this->assertSame(['now'], TestJob::$handled);
        $this->assertSame([], $this->store->jobs);
    }

    public function testSyncModeRunsPushedJobs(): void
    {
        $this->assertNull($this->queue->sync()->push(new RecordingJob('sync')));
        $this->assertSame(['sync'], TestJob::$handled);
        $this->assertSame([], $this->store->jobs);
    }

    public function testSyncModeFromFilter(): void
    {
        Filters\expectApplied('coretik/queue/sync')->andReturn(true);

        $this->queue->push(new RecordingJob('filtered'));

        $this->assertSame(['filtered'], TestJob::$handled);
    }

    public function testWorkRunsAvailableJobsInOrder(): void
    {
        $this->queue->push(new RecordingJob('first'));
        $this->queue->push(new RecordingJob('later'), HOUR_IN_SECONDS);
        $this->queue->push(new RecordingJob('second'));

        Actions\expectDone('coretik/queue/done')->twice();
        $this->assertSame(2, $this->queue->work());

        $this->assertSame(['first', 'second'], TestJob::$handled);
        $this->assertSame(['pending' => 1, 'running' => 0, 'done' => 2, 'failed' => 0], $this->queue->stats());
    }

    public function testWorkStopsAtTheLimit(): void
    {
        foreach (['a', 'b', 'c'] as $name) {
            $this->queue->push(new RecordingJob($name));
        }

        $this->assertSame(2, $this->queue->limit(2)->work());
        $this->assertSame(1, $this->queue->work(null, 5));
        $this->assertSame(['a', 'b', 'c'], TestJob::$handled);
    }

    public function testWorkFiltersByQueue(): void
    {
        $this->queue->push(new RecordingJob('mail', 0, 3, 'mail'));
        $this->queue->push(new RecordingJob('other'));

        $this->queue->work('mail');

        $this->assertSame(['mail'], TestJob::$handled);
    }

    public function testFailedAttemptIsRetriedAfterBackoff(): void
    {
        $id = $this->queue->push(new RecordingJob('flaky', 1));

        Actions\expectDone('coretik/queue/retrying')->once();
        $this->queue->work();

        $job = $this->store->find($id);
        $this->assertSame(QueuedJob::PENDING, $job->status);
        $this->assertSame(1, $job->attempts);
        $this->assertGreaterThanOrEqual(time() + MINUTE_IN_SECONDS - 1, $job->availableAt);
        $this->assertStringContainsString('Attempt 1 of flaky', $job->error);

        $this->availableNow();
        $this->queue->work();

        $this->assertSame(QueuedJob::DONE, $this->store->find($id)->status);
        $this->assertSame(['flaky'], TestJob::$handled);
    }

    public function testJobFailsAfterItsLastAttempt(): void
    {
        $id = $this->queue->push(new RecordingJob('broken', 10, 2));

        $this->queue->work();
        $this->availableNow();
        Actions\expectDone('coretik/queue/failed')->once();
        $this->queue->work();

        $job = $this->store->find($id);
        $this->assertSame(QueuedJob::FAILED, $job->status);
        $this->assertSame(2, $job->attempts);
        $this->assertSame('Attempt 2 of broken', TestJob::$failed['broken']);
    }

    public function testUnknownJobClassFails(): void
    {
        $id = $this->store->push('default', 'Missing\\Job', [], time());

        $this->queue->work();

        $job = $this->store->find($id);
        $this->assertSame(QueuedJob::FAILED, $job->status);
        $this->assertStringContainsString('Unknown job class', $job->error);
    }

    public function testStaleJobsAreReleased(): void
    {
        $id = $this->queue->push(new RecordingJob('killed'));
        $this->store->set($id, ['status' => QueuedJob::RUNNING, 'attempts' => 1, 'reservedAt' => time() - 3600]);

        $this->queue->work();

        $job = $this->store->find($id);
        $this->assertSame(QueuedJob::DONE, $job->status);
        $this->assertSame(2, $job->attempts);
    }

    public function testStaleJobAfterItsLastAttemptFails(): void
    {
        $id = $this->queue->push(new RecordingJob('killed', 0, 1));
        $this->store->set($id, ['status' => QueuedJob::RUNNING, 'attempts' => 1, 'reservedAt' => time() - 3600]);

        $this->queue->work();

        $this->assertSame(QueuedJob::FAILED, $this->store->find($id)->status);
        $this->assertSame([], TestJob::$handled);
        $this->assertArrayHasKey('killed', TestJob::$failed);
    }

    public function testRetryFailedJobsOfAGroup(): void
    {
        $a = $this->queue->push(new RecordingJob('a', 1, 1), 0, 'g1');
        $b = $this->queue->push(new RecordingJob('b', 1, 1), 0, 'g2');
        $this->queue->work();

        $this->assertSame(1, $this->queue->retry('g1'));
        $this->queue->work();

        $this->assertSame(QueuedJob::DONE, $this->store->find($a)->status);
        $this->assertSame(QueuedJob::FAILED, $this->store->find($b)->status);
        $this->assertSame(['pending' => 0, 'running' => 0, 'done' => 1, 'failed' => 0], $this->queue->stats('g1'));
    }

    public function testOldJobsArePurged(): void
    {
        $done = $this->queue->push(new RecordingJob('old'));
        $failed = $this->queue->push(new RecordingJob('old-failed', 5, 1));
        $this->queue->work();
        $this->store->set($done, ['finishedAt' => time() - 8 * DAY_IN_SECONDS]);
        $this->store->set($failed, ['finishedAt' => time() - 8 * DAY_IN_SECONDS]);

        $this->queue->work();

        $this->assertNull($this->store->find($done));
        $this->assertNotNull($this->store->find($failed), 'Failed jobs are kept 30 days');
    }
}
