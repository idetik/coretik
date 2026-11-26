<?php

namespace Coretik\Services\Migrations;

use Coretik\Services\Admin\Menu;

/**
 * Back-office page of the migrations (Coretik > Migrations by default): migrations and seeders with the status of
 * their last run, run the pending migrations, run (again) one migration or seeder. The messages of the run are shown
 * on the page after the redirection.
 *
 * Registered by Migrations::listen(), options with Migrations::admin([...]) or disabled with Migrations::admin(false).
 */
class Admin
{
    public const VIEWS = __DIR__ . '/views/';

    public const DEFAULTS = [
        'slug' => 'coretik-migrations',
        'title' => 'Migrations',
        'capability' => 'manage_options',
        // Parent menu slug (Coretik menu by default, 'tools.php'…), null for a top level menu
        'parent' => Menu::SLUG,
        'icon' => 'dashicons-database',
        'position' => 81,
    ];

    public const STATUS_LABELS = [
        Result::DONE => 'Effectuée',
        Result::FAILED => 'En échec',
        Result::SKIPPED => 'Déjà effectuée',
    ];

    /** Results of the last run, kept for the next page of the user (seconds) */
    public const RESULTS_TTL = 300;

    /** @var array<string, mixed> */
    protected array $options;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(protected Migrations $migrations, array $options = [])
    {
        $this->options = $options + static::DEFAULTS;
    }

    public function hooks(): void
    {
        if (Menu::SLUG === $this->options['parent']) {
            Menu::register();
        }
        \add_action('admin_menu', [$this, 'menu']);
        foreach (['run', 'migrate'] as $action) {
            \add_action('admin_post_' . $this->action($action), [$this, $action]);
        }
    }

    public function option(string $name): mixed
    {
        return $this->options[$name] ?? null;
    }

    public function migrations(): Migrations
    {
        return $this->migrations;
    }

    public function action(string $name): string
    {
        return str_replace('-', '_', (string)$this->options['slug']) . '_' . $name;
    }

    public function menu(): void
    {
        $args = [$this->options['title'], $this->options['title'], $this->options['capability'], $this->options['slug'], [$this, 'page']];
        $this->options['parent']
            ? \add_submenu_page($this->options['parent'], ...$args)
            : \add_menu_page(...[...$args, $this->options['icon'], $this->options['position']]);
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
     * Lines of the page: key, description, seeder, last run
     *
     * @return array<int, array{key: string, description: string, seeder: bool, status: array{status: string, ran_at: int, message: string}|null}>
     */
    public function rows(): array
    {
        return array_map(fn (string $key) => [
            'key' => $key,
            'description' => $this->migrations->description($key),
            'seeder' => $this->migrations->isSeeder($key),
            'status' => $this->migrations->status($key),
        ], $this->migrations->keys());
    }

    protected function authorize(): void
    {
        if (!\current_user_can($this->options['capability'])) {
            \wp_die('Vous n\'avez pas les droits pour lancer les migrations.', '', ['response' => 403]);
        }
    }

    public function page(): void
    {
        $this->authorize();

        $admin = $this;
        $rows = $this->rows();
        $pending = $this->migrations->pending();
        $results = $this->pullResults();

        include static::VIEWS . 'page.php';
    }

    /**
     * One migration or seeder (key), again when done with force=1
     */
    public function run(): void
    {
        $this->authorize();
        \check_admin_referer($this->action('run'));

        $key = \sanitize_text_field(\wp_unslash((string)($_GET['key'] ?? '')));
        $this->back($this->handle([$key], !empty($_GET['force'])));
    }

    /**
     * The pending migrations
     */
    public function migrate(): void
    {
        $this->authorize();
        \check_admin_referer($this->action('migrate'));

        $this->back($this->handle($this->migrations->pending()));
    }

    /**
     * Runs the migrations, errors included in the results (unknown key, unexpected exception)
     *
     * @param string[] $keys
     * @return Result[]
     */
    public function handle(array $keys, bool $force = false): array
    {
        $results = [];
        foreach ($keys as $key) {
            if (!$this->migrations->has($key)) {
                $results[] = new Result($key, Result::FAILED, 'Migration inconnue');
                break;
            }

            try {
                $results[] = $result = $this->migrations->run($key, $force);
            } catch (\Throwable $exception) {
                $results[] = new Result($key, Result::FAILED, sprintf('%s: %s', get_class($exception), $exception->getMessage()));
                break;
            }

            if ($result->failed()) {
                break;
            }
        }

        return $results;
    }

    /**
     * @param Result[] $results
     */
    protected function back(array $results): void
    {
        \set_transient($this->resultsKey(), array_map(fn (Result $result) => $result->toArray(), $results), static::RESULTS_TTL);
        \wp_safe_redirect($this->url(['done' => 1]));
        exit;
    }

    /**
     * Results of the last run of the user, once
     *
     * @return Result[]
     */
    public function pullResults(): array
    {
        $results = \get_transient($this->resultsKey());
        if (!\is_array($results)) {
            return [];
        }

        \delete_transient($this->resultsKey());
        return array_map(fn ($result) => Result::fromArray((array)$result), $results);
    }

    protected function resultsKey(): string
    {
        return $this->action('results') . '_' . \get_current_user_id();
    }
}
