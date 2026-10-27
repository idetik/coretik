<?php

namespace Coretik\Tests\Integration;

use Coretik\Core\Builders\Taxonomy;
use Coretik\Core\Models\Handlers\ScheduledEventsHandler;

/**
 * "session" post type (tests/Integration/bootstrap.php): "starts" at the meta starts_at (date string),
 * "ends" at the meta ends_at (timestamp), not triggered when the meta cancelled is set
 */
class ScheduledEventsTest extends IntegrationTestCase
{
    private const HOOK = 'coretik/scheduled_events/session';

    private ScheduledEventsHandler $handler;
    private array $triggered = [];

    public function set_up(): void
    {
        parent::set_up();

        $this->handler = coretik()->schema('session')->getHandler(ScheduledEventsHandler::class);
        $this->handler->flush();

        $this->triggered = [];
        foreach (['starts', 'ends'] as $event) {
            add_action('coretik/model/session/' . $event, function ($model, $args) use ($event) {
                $this->triggered[] = [$event, $model->id(), $args['date']->getTimestamp()];
            }, 10, 2);
        }
    }

    private function date(int $timestamp): string
    {
        return wp_date('Y-m-d H:i:s', $timestamp);
    }

    private function session(array $metas = [], string $status = 'publish'): int
    {
        $id = self::factory()->post->create(['post_type' => 'session', 'post_status' => $status]);
        foreach ($metas as $key => $value) {
            update_post_meta($id, $key, $value);
        }
        $this->handler->flush();
        return $id;
    }

    private function next(int $id, string $event): int|false
    {
        return wp_next_scheduled(self::HOOK, [$id, $event]);
    }

    public function testFutureDatesAreScheduled(): void
    {
        $starts = time() + HOUR_IN_SECONDS;
        $ends = time() + DAY_IN_SECONDS;
        $id = $this->session(['starts_at' => $this->date($starts), 'ends_at' => $ends]);

        $this->assertSame($starts, $this->next($id, 'starts'));
        $this->assertSame($ends, $this->next($id, 'ends'));
    }

    public function testPastAndEmptyDatesAreNotScheduled(): void
    {
        $id = $this->session(['starts_at' => $this->date(time() - 60), 'ends_at' => '']);

        $this->assertFalse($this->next($id, 'starts'));
        $this->assertFalse($this->next($id, 'ends'));
    }

    public function testChangedDatesAreRescheduled(): void
    {
        $id = $this->session(['starts_at' => $this->date(time() + HOUR_IN_SECONDS)]);

        $later = time() + 2 * HOUR_IN_SECONDS;
        update_post_meta($id, 'starts_at', $this->date($later));
        $this->handler->flush();
        $this->assertSame($later, $this->next($id, 'starts'));

        delete_post_meta($id, 'starts_at');
        $this->handler->flush();
        $this->assertFalse($this->next($id, 'starts'));
    }

    public function testOneEventPerPostAndModelEvent(): void
    {
        $id = $this->session(['starts_at' => $this->date(time() + HOUR_IN_SECONDS)]);
        $this->handler->schedule($id);
        $this->handler->syncAll();

        $events = array_filter(
            _get_cron_array(),
            fn ($hooks) => isset($hooks[self::HOOK])
        );
        $this->assertCount(1, $events);
    }

    public function testInactivePostsHaveNoEvents(): void
    {
        $trashed = $this->session(['starts_at' => $this->date(time() + HOUR_IN_SECONDS)]);
        wp_trash_post($trashed);
        $this->handler->flush();
        $this->assertFalse($this->next($trashed, 'starts'));

        $deleted = $this->session(['starts_at' => $this->date(time() + HOUR_IN_SECONDS)]);
        wp_delete_post($deleted, true);
        $this->handler->flush();
        $this->assertFalse($this->next($deleted, 'starts'));

        $archived = $this->session(['starts_at' => $this->date(time() + HOUR_IN_SECONDS)]);
        wp_update_post(['ID' => $archived, 'post_status' => 'archive']);
        $this->handler->flush();
        $this->assertFalse($this->next($archived, 'starts'));
    }

    public function testDueEventTriggersTheModelEvent(): void
    {
        $id = $this->session();
        $starts = time() - 30;
        update_post_meta($id, 'starts_at', $this->date($starts));

        do_action(self::HOOK, $id, 'starts');

        $this->assertSame([['starts', $id, $starts]], $this->triggered);
    }

    public function testEventMovedLaterIsNotTriggeredButRescheduled(): void
    {
        $later = time() + HOUR_IN_SECONDS;
        $id = $this->session(['ends_at' => $later]);
        wp_clear_scheduled_hook(self::HOOK, [$id, 'ends']);

        do_action(self::HOOK, $id, 'ends');

        $this->assertSame([], $this->triggered);
        $this->assertSame($later, $this->next($id, 'ends'));
    }

    public function testGuardAndInactivePostsSkipTheEvent(): void
    {
        $cancelled = $this->session(['ends_at' => time() - 30, 'cancelled' => 1]);
        do_action(self::HOOK, $cancelled, 'ends');

        $trashed = $this->session(['ends_at' => time() - 30]);
        wp_trash_post($trashed);
        do_action(self::HOOK, $trashed, 'ends');

        do_action(self::HOOK, $this->session(['ends_at' => time() - 30]), 'unknown');

        $this->assertSame([], $this->triggered);
    }

    public function testSyncRestoresLostEvents(): void
    {
        $starts = time() + HOUR_IN_SECONDS;
        $id = $this->session(['starts_at' => $this->date($starts)]);
        wp_clear_scheduled_hook(self::HOOK, [$id, 'starts']);

        do_action(self::HOOK . '/sync');

        $this->assertSame($starts, $this->next($id, 'starts'));
        $this->assertSame($this->handler->fingerprint(), get_option($this->handler->syncOption()));
    }

    public function testSyncRunsOnceWhenTheEventsChange(): void
    {
        wp_clear_scheduled_hook(self::HOOK . '/sync');
        delete_option($this->handler->syncOption());
        $this->handler->actions();

        $sync = wp_get_scheduled_event(self::HOOK . '/sync');
        $this->assertNotFalse($sync);
        $this->assertFalse($sync->schedule, 'single event, not recurring');

        // Synced with the same events: nothing to do
        do_action(self::HOOK . '/sync');
        wp_clear_scheduled_hook(self::HOOK . '/sync');
        $this->handler->actions();
        $this->assertFalse(wp_next_scheduled(self::HOOK . '/sync'));
    }

    public function testRecurringSyncIsOptIn(): void
    {
        update_option($this->handler->syncOption(), $this->handler->fingerprint());
        wp_clear_scheduled_hook(self::HOOK . '/sync');

        $this->handler->syncEvery('hourly');
        try {
            $this->handler->actions();
            $this->assertSame('hourly', wp_get_scheduled_event(self::HOOK . '/sync')->schedule);
        } finally {
            $this->handler->syncEvery(null);
        }

        // Disabled again: the recurring event is removed
        $this->handler->actions();
        $this->assertFalse(wp_next_scheduled(self::HOOK . '/sync'));
    }

    public function testSyncReadsEveryActivePostByBatches(): void
    {
        $starts = time() + HOUR_IN_SECONDS;
        $ids = array_map(fn () => $this->session(['starts_at' => $this->date($starts)]), range(1, 5));
        $draft = $this->session(['starts_at' => $this->date($starts)], 'draft');
        $trashed = $this->session(['starts_at' => $this->date($starts)]);
        wp_trash_post($trashed);
        $this->handler->flush();

        $models = coretik()->schema('session')->models();
        foreach ([...$ids, $draft, $trashed] as $id) {
            wp_clear_scheduled_hook(self::HOOK, [$id, 'starts']);
            $models->offsetUnset($id);
        }

        $this->handler->syncBatchSize(2);
        try {
            $this->handler->syncAll();
        } finally {
            $this->handler->syncBatchSize(200);
        }

        foreach ([...$ids, $draft] as $id) {
            $this->assertSame($starts, $this->next($id, 'starts'));
            $this->assertFalse($models->offsetExists($id), 'models are released after each batch');
        }
        $this->assertFalse($this->next($trashed, 'starts'));
    }

    public function testFrozenHandlerIgnoresChanges(): void
    {
        $id = $this->session();
        $this->handler->pause(function () use ($id) {
            update_post_meta($id, 'starts_at', $this->date(time() + HOUR_IN_SECONDS));
        });
        $this->handler->flush();

        $this->assertFalse($this->next($id, 'starts'));
    }

    public function testOnlyPostTypesAreSupported(): void
    {
        $this->expectException(\LogicException::class);
        ScheduledEventsHandler::make()->handle(new Taxonomy('session_category', 'session'));
    }
}
