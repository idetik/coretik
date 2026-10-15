<?php

namespace Coretik\Core\Models\Handlers;

use Coretik\Core\Builders\Handler;
use Coretik\Core\Builders\Interfaces\BuilderInterface;
use Coretik\Core\Models\Interfaces\ModelInterface;
use DateTimeImmutable;
use DateTimeInterface;
use LogicException;

/**
 * Trigger a model event at a date read on the model (a meta, an ACF field…), with WP-Cron.
 *
 * PostType::make('camp')
 *     ->handler(
 *         ScheduledEventsHandler::make()
 *             ->at('registrationsOpened', fn ($camp) => $camp->opens_at)
 *             ->at('registrationsClosed', fn ($camp) => $camp->closes_at)
 *             ->when(fn ($camp, string $event) => $camp->isEffective($event))
 *     )
 *     ->addToSchema();
 *
 * // Listen like any model event: $model->on('registrationsOpened', …) or add_action('coretik/model/camp/registrationsOpened', …)
 *
 * - One single WP-Cron event per post and model event, (re)scheduled when the post, its metas or its status change
 *   (at the end of the request: the dates are read once every field is saved, ACF included). Call flush() to apply now.
 * - Past or empty dates have no event, inactive posts (trash, archive…) have no event.
 * - When it runs, the date is read again: the model event is triggered only if it is still due and when() agrees.
 * - Existing posts: a sync of every post runs once when the declared events change (first deploy, new event).
 *   It runs by batches, without meta query. syncAll() can be called after an import or a database restore,
 *   syncEvery() makes it recurring (opt-in: it reads every post of the type).
 *
 * WP-Cron runs on page visits: without a system cron, an event runs at the first request after its date.
 * Post types only.
 *
 * @phpstan-consistent-constructor
 */
class ScheduledEventsHandler extends Handler
{
    /** @var array<string, callable(ModelInterface): (DateTimeInterface|int|string|null)> */
    protected array $events = [];

    /** @var null|callable(ModelInterface, string, DateTimeInterface): bool */
    protected $guard = null;

    /** @var string[] */
    protected array $inactiveStatuses = ['trash', 'auto-draft', PostStatusArchiveHandler::POST_STATUS_ARCHIVE_NAME];

    protected ?string $syncRecurrence = null;

    protected int $syncBatchSize = 200;

    /** @var array<int, true> Posts to reschedule at the end of the request */
    protected array $dirty = [];

    public static function make(): static
    {
        return new static();
    }

    /**
     * Trigger $event at the date returned by $date (DateTimeInterface, timestamp, date string in the site timezone, or null)
     */
    public function at(string $event, callable $date): self
    {
        $this->events[$event] = $date;
        return $this;
    }

    /**
     * Last check when an event runs: fn (ModelInterface $model, string $event, DateTimeInterface $date): bool
     */
    public function when(callable $guard): self
    {
        $this->guard = $guard;
        return $this;
    }

    /**
     * Post statuses without events (default: trash, auto-draft, archive)
     */
    public function inactiveStatuses(array $statuses): self
    {
        $this->inactiveStatuses = $statuses;
        return $this;
    }

    /**
     * Opt-in recurring sync of every post (a wp_get_schedules() key), null (default) to sync only when the events change
     */
    public function syncEvery(?string $recurrence): self
    {
        $this->syncRecurrence = $recurrence;
        return $this;
    }

    /**
     * Posts read per sync batch
     */
    public function syncBatchSize(int $size): self
    {
        $this->syncBatchSize = max(1, $size);
        return $this;
    }

    public function handle(BuilderInterface $builder): void
    {
        if ('post' !== $builder->getType()) {
            throw new LogicException(sprintf('%s only supports post types, "%s" is a %s.', static::class, $builder->getName(), $builder->getType()));
        }

        parent::handle($builder);
    }

    /**
     * WP-Cron hook of the model events, args: [post id, event]
     */
    public function hook(): string
    {
        return 'coretik/scheduled_events/' . $this->builder->getName();
    }

    public function syncHook(): string
    {
        return $this->hook() . '/sync';
    }

    /**
     * Option keeping the fingerprint of the events synced last
     */
    public function syncOption(): string
    {
        return 'coretik_scheduled_events_' . $this->builder->getName();
    }

    /**
     * Declared events and inactive statuses: a change requires a sync of the existing posts
     */
    public function fingerprint(): string
    {
        $events = array_keys($this->events);
        $statuses = $this->inactiveStatuses;
        sort($events);
        sort($statuses);
        return md5(\wp_json_encode([$events, $statuses]));
    }

    public function actions(): void
    {
        \add_action($this->hook(), [$this, 'run'], 10, 2);
        \add_action($this->syncHook(), [$this, 'syncAll']);

        \add_action('save_post_' . $this->builder->getName(), [$this, 'markPost'], 10, 1);
        \add_action('transition_post_status', [$this, 'markTransition'], 10, 3);
        \add_action('before_delete_post', [$this, 'markPost'], 10, 1);
        foreach (['added_post_meta', 'updated_post_meta', 'deleted_post_meta'] as $hook) {
            \add_action($hook, [$this, 'markMeta'], 10, 2);
        }
        \add_action('shutdown', [$this, 'flush']);

        $this->scheduleSync();
    }

    /**
     * Recurring sync (opt-in), or a single one when the events changed since the last sync
     */
    protected function scheduleSync(): void
    {
        $scheduled = \wp_get_scheduled_event($this->syncHook());

        // Recurrence changed or disabled
        if ($scheduled && $scheduled->schedule && $scheduled->schedule !== $this->syncRecurrence) {
            \wp_clear_scheduled_hook($this->syncHook());
            $scheduled = false;
        }

        if ($scheduled) {
            return;
        }

        if ($this->syncRecurrence) {
            \wp_schedule_event(time(), $this->syncRecurrence, $this->syncHook());
        } elseif (\get_option($this->syncOption()) !== $this->fingerprint()) {
            \wp_schedule_single_event(time(), $this->syncHook());
        }
    }

    public function freeze(): void
    {
        \remove_action($this->hook(), [$this, 'run'], 10);
        \remove_action($this->syncHook(), [$this, 'syncAll']);

        \remove_action('save_post_' . $this->builder->getName(), [$this, 'markPost'], 10);
        \remove_action('transition_post_status', [$this, 'markTransition'], 10);
        \remove_action('before_delete_post', [$this, 'markPost'], 10);
        foreach (['added_post_meta', 'updated_post_meta', 'deleted_post_meta'] as $hook) {
            \remove_action($hook, [$this, 'markMeta'], 10);
        }
        \remove_action('shutdown', [$this, 'flush']);
    }

    public function markPost($post_id): void
    {
        if ($this->concern((int)$post_id)) {
            $this->dirty[(int)$post_id] = true;
        }
    }

    public function markTransition($new_status, $old_status, $post): void
    {
        if ($new_status !== $old_status && $post instanceof \WP_Post) {
            $this->markPost($post->ID);
        }
    }

    public function markMeta($meta_id, $post_id): void
    {
        $this->markPost($post_id);
    }

    /**
     * Reschedule the posts changed during the request
     */
    public function flush(): void
    {
        $ids = array_keys($this->dirty);
        $this->dirty = [];

        foreach ($ids as $id) {
            $this->schedule($id);
        }
    }

    /**
     * Reschedule every post of the builder, by batches: posts and metas primed in one query each,
     * models and runtime cache released after each batch
     */
    public function syncAll(): void
    {
        $models = \method_exists($this->builder, 'models') ? $this->builder->models() : null;
        $lastId = 0;

        do {
            $ids = $this->nextBatch($lastId);
            if (empty($ids)) {
                break;
            }
            $lastId = (int)end($ids);

            \_prime_post_caches($ids, false, true);
            foreach ($ids as $id) {
                $cached = $models?->offsetExists($id);
                $this->schedule($id);
                if ($models && !$cached && $models->offsetExists($id)) {
                    $models->offsetUnset($id);
                }
            }

            if (\function_exists('wp_cache_supports') && \wp_cache_supports('flush_runtime')) {
                \wp_cache_flush_runtime();
            }
        } while (count($ids) === $this->syncBatchSize);

        \update_option($this->syncOption(), $this->fingerprint(), true);
    }

    /**
     * Post ids after $lastId (keyset pagination: stable while events are scheduled)
     *
     * @return int[]
     */
    protected function nextBatch(int $lastId): array
    {
        global $wpdb;

        $statuses = array_diff(\get_post_stati(), $this->inactiveStatuses, ['inherit']);
        if (empty($statuses)) {
            return [];
        }

        return array_map('intval', $wpdb->get_col($wpdb->prepare(
            sprintf(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = %%s AND ID > %%d AND post_status IN (%s) ORDER BY ID ASC LIMIT %%d",
                implode(',', array_fill(0, count($statuses), '%s'))
            ),
            $this->builder->getName(),
            $lastId,
            ...array_values($statuses),
            ...[$this->syncBatchSize]
        )));
    }

    /**
     * Align the post WP-Cron events with its dates: only the changed events are cleared / scheduled
     */
    public function schedule(int $id): void
    {
        $model = $this->isActive($id) ? $this->builder->model($id, null, true) : null;

        foreach (array_keys($this->events) as $event) {
            $args = [$id, $event];
            $current = \wp_next_scheduled($this->hook(), $args) ?: null;
            $target = $model ? $this->date($model, $event)?->getTimestamp() : null;
            $target = $target && $target > time() ? $target : null;

            if ($current === $target) {
                continue;
            }
            if ($current) {
                \wp_clear_scheduled_hook($this->hook(), $args);
            }
            if ($target) {
                \wp_schedule_single_event($target, $this->hook(), $args);
            }
        }
    }

    /**
     * WP-Cron callback: trigger the model event if it is still due
     */
    public function run($id, $event): void
    {
        $id = (int)$id;
        if (!isset($this->events[$event]) || !$this->isActive($id)) {
            return;
        }

        $model = $this->builder->model($id, null, true);
        $date = $this->date($model, $event);

        // Moved later meanwhile: the new date has its own event
        if (!$date || $date->getTimestamp() > time()) {
            $this->schedule($id);
            return;
        }

        if ($this->guard && !\call_user_func($this->guard, $model, $event, $date)) {
            return;
        }

        $model->trigger($event, ['date' => $date]);
    }

    public function isActive(int $id): bool
    {
        $status = \get_post_status($id);
        return false !== $status && !\in_array($status, $this->inactiveStatuses, true) && $this->concern($id);
    }

    public function date(ModelInterface $model, string $event): ?DateTimeInterface
    {
        $value = \call_user_func($this->events[$event], $model);

        if ($value instanceof DateTimeInterface) {
            return $value;
        }
        if (\is_int($value) && $value > 0) {
            return (new DateTimeImmutable('@' . $value))->setTimezone(\wp_timezone());
        }
        if (\is_string($value) && '' !== $value) {
            try {
                return new DateTimeImmutable($value, \wp_timezone());
            } catch (\Exception) {
                return null;
            }
        }

        return null;
    }
}
