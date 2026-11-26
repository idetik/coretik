<?php

namespace Coretik\Testing;

/**
 * Integration tests bootstrap: installs WordPress in a test database (wp-phpunit), then loads it with the theme and
 * the plugins of the project. A project's tests/Integration/bootstrap.php:
 *
 * require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
 *
 * \Coretik\Testing\Bootstrap::make(dirname(__DIR__, 2))
 *     ->database('my_project_tests')
 *     ->wordpress('web/wp')                       // ABSPATH, relative to the project root
 *     ->content('web/app', 'web/app/plugins')     // WP_CONTENT_DIR, WP_PLUGIN_DIR
 *     ->theme('my-theme')
 *     ->plugins('advanced-custom-fields-pro/acf.php')
 *     ->run();
 *
 * WordPress is configured by src/Testing/wp-tests-config.php, from the constants given here: the test suite installs
 * WordPress in a separate PHP process, which only loads that file (the constants go through an environment variable).
 * The database settings come from the WP_TESTS_DB_HOST, WP_TESTS_DB_NAME, WP_TESTS_DB_USER and WP_TESTS_DB_PASSWORD
 * environment variables first. Its tables are dropped at each run: the name of the database must contain "test".
 *
 * @phpstan-consistent-constructor
 */
class Bootstrap
{
    /**
     * Environment variable read by wp-tests-config.php: JSON {"constants": {...}, "table_prefix": "..."}
     */
    public const ENV = 'CORETIK_TESTS_CONFIG';

    protected string $root;
    protected string $database = 'wordpress_tests';
    protected array $constants = [];
    protected array $plugins = [];
    protected array $hooks = [];
    protected ?string $config = null;
    protected string|false|null $uploads = null;
    protected bool $queueTable = true;
    protected string $tablePrefix = 'wptests_';

    /**
     * @param string $root Project root: relative paths given to the other methods are resolved from it
     */
    public function __construct(string $root)
    {
        $this->root = \rtrim($root, '/\\');
    }

    public static function make(string $root): static
    {
        return new static($root);
    }

    /**
     * Default database name, when WP_TESTS_DB_NAME is not set
     */
    public function database(string $name): static
    {
        $this->database = $name;
        return $this;
    }

    /**
     * WordPress core directory (ABSPATH). Default: vendor/johnpbloch/wordpress-core
     */
    public function wordpress(string $path): static
    {
        return $this->define('ABSPATH', \rtrim($this->path($path), '/\\') . '/');
    }

    /**
     * WP_CONTENT_DIR (themes) and WP_PLUGIN_DIR (default: {content}/plugins)
     */
    public function content(string $path, ?string $plugins = null): static
    {
        $this->define('WP_CONTENT_DIR', $this->path($path));
        return $this->define('WP_PLUGIN_DIR', null === $plugins ? $this->path($path) . '/plugins' : $this->path($plugins));
    }

    /**
     * Theme activated by the tests installation (directory name in WP_CONTENT_DIR/themes)
     */
    public function theme(string $stylesheet): static
    {
        return $this->define('WP_DEFAULT_THEME', $stylesheet);
    }

    /**
     * Plugins loaded on muplugins_loaded, in this order: main file relative to WP_PLUGIN_DIR, or absolute path
     */
    public function plugins(string ...$files): static
    {
        \array_push($this->plugins, ...$files);
        return $this;
    }

    /**
     * Must-use plugins directory (WPMU_PLUGIN_DIR). Default: none, the must-use plugins of the project (logs, security,
     * external services…) are not loaded by the tests
     */
    public function muPlugins(string $path): static
    {
        return $this->define('WPMU_PLUGIN_DIR', $this->path($path));
    }

    /**
     * Any constant of wp-config.php (scalar value)
     */
    public function define(string $name, string|int|float|bool|null $value): static
    {
        $this->constants[$name] = $value;
        return $this;
    }

    public function tablePrefix(string $prefix): static
    {
        $this->tablePrefix = $prefix;
        return $this;
    }

    /**
     * Callback added to a WordPress hook before WordPress is loaded (boot coretik and declare fixtures on
     * after_setup_theme, as a theme would do…)
     */
    public function on(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): static
    {
        $this->hooks[] = [$hook, $callback, $priority, $acceptedArgs];
        return $this;
    }

    /**
     * Uploads directory. Default: a temporary directory, never the project one. False: WordPress default
     */
    public function uploads(string|false $path): static
    {
        $this->uploads = false === $path ? false : $this->path($path);
        return $this;
    }

    /**
     * Creates the coretik queue table ({prefix}coretik_jobs) on init, before the tests (they turn CREATE TABLE into
     * temporary tables). Default: true
     */
    public function queueTable(bool $install = true): static
    {
        $this->queueTable = $install;
        return $this;
    }

    /**
     * Own wp-tests-config.php instead of src/Testing/wp-tests-config.php (the other settings are then ignored, except
     * the plugins, hooks, uploads and queue table)
     */
    public function config(string $file): static
    {
        $this->config = $this->path($file);
        return $this;
    }

    /**
     * Constants defined by wp-tests-config.php: the ones given, then safe defaults
     */
    public function constants(): array
    {
        $env = fn (string $name, string $default) => (string)(\getenv($name) ?: $default);

        return $this->constants + [
            'DB_NAME' => $env('WP_TESTS_DB_NAME', $this->database),
            'DB_USER' => $env('WP_TESTS_DB_USER', 'root'),
            'DB_PASSWORD' => $env('WP_TESTS_DB_PASSWORD', 'root'),
            'DB_HOST' => $env('WP_TESTS_DB_HOST', '127.0.0.1:3307'),
            'DB_CHARSET' => 'utf8mb4',
            'DB_COLLATE' => '',
            'ABSPATH' => $this->root . '/vendor/johnpbloch/wordpress-core/',
            // Missing directory: no must-use plugin
            'WPMU_PLUGIN_DIR' => __DIR__ . '/mu-plugins',
            'WP_DEBUG' => true,
            // No outgoing HTTP request (payment providers, APIs…): mock them with the pre_http_request filter
            'WP_HTTP_BLOCK_EXTERNAL' => true,
            'WP_TESTS_DOMAIN' => 'example.org',
            'WP_TESTS_EMAIL' => 'admin@example.org',
            'WP_TESTS_TITLE' => 'Tests',
            'WP_PHP_BINARY' => \PHP_BINARY,
            'WPLANG' => '',
        ];
    }

    /**
     * Value of the environment variable read by wp-tests-config.php
     */
    public function payload(): string
    {
        $constants = $this->constants();

        // The test suite drops all the tables of the database
        if (!\str_contains(\strtolower((string)$constants['DB_NAME']), 'test')) {
            throw new \RuntimeException(\sprintf(
                'The tests database "%s" would be emptied: its name must contain "test" (WP_TESTS_DB_NAME).',
                $constants['DB_NAME']
            ));
        }

        return (string)\json_encode(['constants' => $constants, 'table_prefix' => $this->tablePrefix], \JSON_THROW_ON_ERROR);
    }

    /**
     * Directory of the WordPress test suite (wp-phpunit/wp-phpunit)
     */
    public function testsDir(): string
    {
        return (string)(\getenv('WP_PHPUNIT__DIR') ?: $this->root . '/vendor/wp-phpunit/wp-phpunit');
    }

    /**
     * Installs and loads WordPress, then the test case classes (WP_UnitTestCase…)
     */
    public function run(): void
    {
        try {
            \putenv(static::ENV . '=' . $this->payload());
        } catch (\RuntimeException $e) {
            echo 'Error: ' . $e->getMessage() . \PHP_EOL;
            exit(1);
        }
        \putenv('WP_PHPUNIT__TESTS_CONFIG=' . ($this->config ?? __DIR__ . '/wp-tests-config.php'));

        require_once $this->testsDir() . '/includes/functions.php';

        $this->addHooks();

        // Loaded in this method scope, as PHPUnit loads a bootstrap file: WordPress globals are declared global there
        require_once $this->testsDir() . '/includes/bootstrap.php';
    }

    protected function addHooks(): void
    {
        $plugins = $this->plugins;
        \tests_add_filter('muplugins_loaded', function () use ($plugins) {
            foreach ($plugins as $file) {
                require_once static::isAbsolute($file) ? $file : \WP_PLUGIN_DIR . '/' . $file;
            }
        });

        if (false !== $this->uploads) {
            $base = $this->uploads ?? \sys_get_temp_dir() . '/coretik-tests-uploads-' . \md5($this->root);
            \tests_add_filter('upload_dir', fn (array $dirs) => ['path' => $base . $dirs['subdir'], 'basedir' => $base] + $dirs);
        }

        if ($this->queueTable) {
            \tests_add_filter('init', fn () => (new \Coretik\Services\Queue\DatabaseStore())->install(true), 0);
        }

        foreach ($this->hooks as [$hook, $callback, $priority, $acceptedArgs]) {
            \tests_add_filter($hook, $callback, $priority, $acceptedArgs);
        }
    }

    protected function path(string $path): string
    {
        return static::isAbsolute($path) ? \rtrim($path, '/\\') : $this->root . '/' . \trim($path, '/\\');
    }

    protected static function isAbsolute(string $path): bool
    {
        return \str_starts_with($path, '/') || \str_starts_with($path, '\\') || 1 === \preg_match('#^[A-Za-z]:[/\\\\]#', $path);
    }
}
