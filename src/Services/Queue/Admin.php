<?php

namespace Coretik\Services\Queue;

use Coretik\Services\Admin\Menu;

/**
 * Back-office page of the queue (Coretik > File d'attente by default): worker health, jobs by status, filters
 * (status, queue, group), last error and payload of each job, retry / delete a job, retry the failed jobs, run the
 * worker now.
 *
 * Registered by Queue::listen(), options with Queue::admin([...]) or disabled with Queue::admin(false).
 */
class Admin
{
    public const VIEWS = __DIR__ . '/views/';
    public const STYLES = __DIR__ . '/views/style.css';

    public const DEFAULTS = [
        'slug' => 'coretik-queue',
        'title' => 'File d\'attente',
        'capability' => 'manage_options',
        // Parent menu slug (Coretik menu by default, 'tools.php'…), null for a top level menu
        'parent' => Menu::SLUG,
        'icon' => 'dashicons-clock',
        'position' => 81,
        'per_page' => 50,
        // Worker considered stopped after this delay without run (seconds)
        'late_after' => 5 * MINUTE_IN_SECONDS,
    ];

    public const STATUS_LABELS = [
        QueuedJob::PENDING => 'En attente',
        QueuedJob::RUNNING => 'En cours',
        QueuedJob::DONE => 'Terminées',
        QueuedJob::FAILED => 'En échec',
    ];

    /**
     * Status of one job
     */
    public const JOB_STATUS_LABELS = [
        QueuedJob::PENDING => 'En attente',
        QueuedJob::RUNNING => 'En cours',
        QueuedJob::DONE => 'Terminée',
        QueuedJob::FAILED => 'En échec',
    ];

    /** @var array<string, mixed> */
    protected array $options;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(protected Queue $queue, array $options = [])
    {
        $this->options = $options + static::DEFAULTS;
    }

    public function hooks(): void
    {
        if (Menu::SLUG === $this->options['parent']) {
            Menu::register();
        }
        \add_action('admin_menu', [$this, 'menu']);
        foreach (['retry', 'delete', 'work'] as $action) {
            \add_action('admin_post_' . $this->action($action), [$this, $action]);
        }
    }

    public function option(string $name): mixed
    {
        return $this->options[$name] ?? null;
    }

    public function action(string $name): string
    {
        return str_replace('-', '_', (string)$this->options['slug']) . '_' . $name;
    }

    public function menu(): void
    {
        $args = [$this->options['title'], $this->options['title'], $this->options['capability'], $this->options['slug'], [$this, 'page']];
        $hook = $this->options['parent']
            ? \add_submenu_page($this->options['parent'], ...$args)
            : \add_menu_page(...[...$args, $this->options['icon'], $this->options['position']]);

        if ($hook) {
            \add_action('admin_print_styles-' . $hook, [$this, 'styles']);
        }
    }

    public function styles(): void
    {
        printf("<style>\n%s\n</style>\n", (string)file_get_contents(static::STYLES));
    }

    /**
     * Page URL, admin.php or under its parent (tools.php…)
     *
     * @param array<string, scalar|null> $args
     */
    public function url(array $args = []): string
    {
        $base = $this->options['parent'] && str_ends_with((string)$this->options['parent'], '.php') ? (string)$this->options['parent'] : 'admin.php';
        return \add_query_arg(array_filter(['page' => $this->options['slug']] + $args, fn ($value) => null !== $value && '' !== $value), \admin_url($base));
    }

    /**
     * admin-post.php URL of an action, with its nonce
     *
     * @param array<string, scalar|null> $args
     */
    public function actionUrl(string $action, array $args = []): string
    {
        return \wp_nonce_url(
            \add_query_arg(array_filter(['action' => $this->action($action)] + $args, fn ($value) => null !== $value && '' !== $value), \admin_url('admin-post.php')),
            $this->action($action)
        );
    }

    /**
     * Filters of the query string: known status, queue, group
     *
     * @return array{status: string, queue: string, group: string}
     */
    public static function filters(array $query): array
    {
        $query = \wp_unslash($query);
        $status = (string)($query['status'] ?? '');

        return [
            'status' => in_array($status, QueuedJob::STATUSES, true) ? $status : '',
            'queue' => \sanitize_text_field((string)($query['queue'] ?? '')),
            'group' => \sanitize_text_field((string)($query['group'] ?? '')),
        ];
    }

    /**
     * Worker health: last run, next WP-Cron event, late when jobs are waiting and the worker did not run for a while
     *
     * @return array{last: ?int, next: int|false, late: bool, cron_disabled: bool}
     */
    public function health(int $pending): array
    {
        $last = $this->queue->lastRun();
        $next = \wp_next_scheduled(Queue::HOOK);

        return [
            'last' => $last,
            'next' => $next,
            'late' => $pending > 0 && (!$last || time() - $last > (int)$this->options['late_after']),
            'cron_disabled' => defined('DISABLE_WP_CRON') && \DISABLE_WP_CRON,
        ];
    }

    /**
     * Job class without namespace
     */
    public static function jobName(string $class): string
    {
        $position = strrpos($class, '\\');
        return false === $position ? $class : substr($class, $position + 1);
    }

    public static function date(?int $timestamp): string
    {
        if (!$timestamp) {
            return '—';
        }

        $diff = \human_time_diff($timestamp, time());
        return sprintf('%s (%s)', \wp_date('d/m/Y H:i:s', $timestamp), $timestamp > time() ? 'dans ' . $diff : 'il y a ' . $diff);
    }

    /**
     * "il y a 3 minutes", "dans 54 secondes", "—"
     */
    public static function relative(?int $timestamp): string
    {
        if (!$timestamp) {
            return '—';
        }

        $diff = \human_time_diff($timestamp, time());
        return $timestamp > time() ? 'dans ' . $diff : 'il y a ' . $diff;
    }

    /**
     * Full date for the title attributes
     */
    public static function fullDate(?int $timestamp): string
    {
        return $timestamp ? \wp_date('d/m/Y H:i:s', $timestamp) : '';
    }

    /**
     * Page URL of the filters form (admin.php or the parent page)
     */
    public function formAction(): string
    {
        return \admin_url($this->options['parent'] && str_ends_with((string)$this->options['parent'], '.php') ? (string)$this->options['parent'] : 'admin.php');
    }

    protected function authorize(): void
    {
        if (!\current_user_can($this->options['capability'])) {
            \wp_die('Vous n\'avez pas les droits pour gérer la file d\'attente.', '', ['response' => 403]);
        }
    }

    public function page(): void
    {
        $this->authorize();

        $admin = $this;
        $filters = static::filters($_GET);
        $paged = max(1, (int)($_GET['paged'] ?? 1));
        $perPage = max(1, (int)$this->options['per_page']);
        $total = $this->queue->store()->count($filters);
        $jobs = $this->queue->store()->list($filters, $perPage, ($paged - 1) * $perPage);
        $pages = (int)ceil($total / $perPage);
        $stats = $this->queue->stats($filters['group'] ?: null);
        $queues = $this->queue->store()->queues();
        $health = $this->health($stats[QueuedJob::PENDING]);
        $notice = \sanitize_key((string)($_GET['done'] ?? ''));
        $count = (int)($_GET['count'] ?? 0);

        include static::VIEWS . 'page.php';
    }

    /**
     * Failed jobs run again: one job (id), those of a group, or all
     */
    public function retry(): void
    {
        $this->authorize();
        \check_admin_referer($this->action('retry'));

        $id = (int)($_GET['id'] ?? 0);
        $group = \sanitize_text_field(\wp_unslash((string)($_GET['group'] ?? '')));
        $count = $this->queue->retry($group ?: null, $id ?: null);

        $this->back('retried', $count);
    }

    public function delete(): void
    {
        $this->authorize();
        \check_admin_referer($this->action('delete'));

        $this->back('deleted', $this->queue->delete((int)($_GET['id'] ?? 0)) ? 1 : 0);
    }

    /**
     * Runs the worker now (same limits as WP-Cron)
     */
    public function work(): void
    {
        $this->authorize();
        \check_admin_referer($this->action('work'));

        $this->back('worked', $this->queue->work());
    }

    protected function back(string $done, int $count): void
    {
        $filters = static::filters($_GET);
        \wp_safe_redirect($this->url($filters + ['done' => $done, 'count' => $count]));
        exit;
    }
}
