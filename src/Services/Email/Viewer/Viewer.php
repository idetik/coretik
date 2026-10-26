<?php

namespace Coretik\Services\Email\Viewer;

/**
 * Back-office page listing the emails of the application (Catalog) with their preview:
 * envelope (from, reply-to, to, subject), rendered HTML in an iframe (desktop / mobile width), test sending to oneself.
 *
 * (new Viewer(new MyCatalog()))->hooks();
 * (new Viewer(new MyCatalog(), ['parent' => 'tools.php', 'capability' => 'edit_others_posts']))->hooks();
 *
 * URLs: admin.php?page={slug}&mail={key}&{context fields}, the frame and the test sending through admin-post.php.
 */
class Viewer
{
    public const VIEWS = __DIR__ . '/views/';
    public const STYLES = __DIR__ . '/views/style.css';

    public const DEFAULTS = [
        'slug' => 'coretik-emails',
        'title' => 'Emails',
        'capability' => 'manage_options',
        'icon' => 'dashicons-email-alt',
        'position' => 81,
        // Parent menu slug: submenu instead of a top level menu
        'parent' => null,
    ];

    /**
     * Query vars of the viewer itself, never part of the context
     */
    protected const RESERVED = ['page', 'mail', 'action', '_wpnonce', '_wp_http_referer', 'sent'];

    protected array $options;

    public function __construct(protected Catalog $catalog, array $options = [])
    {
        $this->options = $options + static::DEFAULTS;
    }

    public function hooks(): void
    {
        \add_action('admin_menu', [$this, 'menu']);
        \add_action('admin_post_' . $this->action('frame'), [$this, 'frame']);
        \add_action('admin_post_' . $this->action('send'), [$this, 'send']);
    }

    public function option(string $name): mixed
    {
        return $this->options[$name] ?? null;
    }

    public function action(string $name): string
    {
        return str_replace('-', '_', $this->options['slug']) . '_' . $name;
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

    public function url(array $args = []): string
    {
        return \add_query_arg(['page' => $this->options['slug']] + $args, \admin_url('admin.php'));
    }

    public function frameUrl(string $key, array $query = []): string
    {
        return \add_query_arg(['action' => $this->action('frame'), 'mail' => $key] + $query, \admin_url('admin-post.php'));
    }

    public function sendUrl(string $key, array $query = []): string
    {
        return \wp_nonce_url(
            \add_query_arg(['action' => $this->action('send'), 'mail' => $key] + $query, \admin_url('admin-post.php')),
            $this->action('send')
        );
    }

    /**
     * Entries by group, in the catalog order (groups in order of first appearance)
     *
     * @param Entry[] $entries
     * @return array<string, Entry[]>
     */
    public static function groups(array $entries): array
    {
        $groups = [];
        foreach ($entries as $entry) {
            $groups[$entry->group][] = $entry;
        }

        return $groups;
    }

    /**
     * The requested entry, the first one when unknown
     *
     * @param Entry[] $entries
     */
    public static function resolve(array $entries, string $key): ?Entry
    {
        foreach ($entries as $entry) {
            if ($entry->key === $key) {
                return $entry;
            }
        }

        return $entries[0] ?? null;
    }

    /**
     * Context fields of the query string: scalar values, sanitized
     *
     * @return array<string, string>
     */
    public static function contextQuery(array $query): array
    {
        $context = [];
        foreach (\wp_unslash($query) as $name => $value) {
            if (is_scalar($value) && !in_array($name, static::RESERVED, true)) {
                $context[\sanitize_key((string)$name)] = \sanitize_text_field((string)$value);
            }
        }

        return $context;
    }

    protected function authorize(): void
    {
        if (!\current_user_can($this->options['capability'])) {
            \wp_die('Vous n\'avez pas les droits pour voir les emails.', 403);
        }
    }

    /**
     * @return array{0: Entry[], 1: ?Entry, 2: array<string, string>, 3: array}
     */
    protected function request(): array
    {
        $entries = array_values($this->catalog->entries());
        $entry = static::resolve($entries, (string)($_GET['mail'] ?? ''));
        $query = static::contextQuery($_GET);

        return [$entries, $entry, $query, $this->catalog->context($query)];
    }

    public function page(): void
    {
        $this->authorize();

        [$entries, $entry, $query, $context] = $this->request();
        $preview = $entry ? $this->catalog->preview($entry->key, $context) : null;
        $viewer = $this;
        $catalog = $this->catalog;
        $groups = static::groups($entries);
        $sent = isset($_GET['sent']) ? (bool)$_GET['sent'] : null;

        include static::VIEWS . 'page.php';
    }

    /**
     * Email HTML alone, loaded by the iframe of the page
     */
    public function frame(): void
    {
        $this->authorize();

        [, $entry, , $context] = $this->request();
        $preview = $entry ? $this->catalog->preview($entry->key, $context) : null;
        if (!$preview) {
            \wp_die('Aperçu indisponible.', 404);
        }

        \nocache_headers();
        header('Content-Type: text/html; charset=' . \get_bloginfo('charset'));
        header('X-Frame-Options: SAMEORIGIN');
        print($preview->html);
        exit;
    }

    /**
     * Test sending to the current user, back to the page with the result
     */
    public function send(): void
    {
        $this->authorize();
        \check_admin_referer($this->action('send'));

        [, $entry, $query, $context] = $this->request();
        $to = (string)\wp_get_current_user()->user_email;
        $sent = $entry && \is_email($to) && $this->catalog->sendTest($entry->key, $context, $to);

        \wp_safe_redirect($this->url(['mail' => $entry?->key, 'sent' => (int)$sent] + $query));
        exit;
    }

    public function styles(): void
    {
        printf('<style>%s</style>', (string)file_get_contents(static::STYLES));
    }
}
