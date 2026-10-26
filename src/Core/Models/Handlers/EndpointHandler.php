<?php

namespace Coretik\Core\Models\Handlers;

use Coretik\Core\Builders\Handler;
use Coretik\Core\Models\Interfaces\ModelInterface;

/**
 * Endpoint of the single pages of a post type: /{slug}/{post}/{endpoint}/, ?{endpoint}=1 without pretty permalinks.
 *
 * PostType::make('camp')
 *     ->handler(
 *         EndpointHandler::make('inscription', fn (Camp $camp) => $camp->isOpen() || exit(wp_safe_redirect(…)))
 *             ->template('camp-registration.php')
 *             ->noIndex()
 *     )
 *     ->addToSchema();
 *
 * $handler->url($camp); // https://example.org/camp/ete-2027/inscription/
 * $handler->isRequested(); // in templates, hooks…
 *
 * - The callable runs on template_redirect (priority 0, before redirect_canonical()) with the model and the value
 *   of the endpoint ("" without value): guard, redirection, data loading…
 * - Requested anywhere else (another post type sharing the mask, an archive…) or with a value (/inscription/foo/)
 *   unless acceptsValue(): 404, the endpoint never duplicates a page.
 * - The rewrite rules are flushed once when the endpoint is missing from them (first deploy, new endpoint).
 * - The mask is EP_PERMALINK by default, shared by posts and most post types: give the post type its own mask
 *   (rewrite.ep_mask) and setMask() to only add the rules there.
 *
 * Post types only. Several post types can declare the same endpoint.
 *
 * @phpstan-consistent-constructor
 */
class EndpointHandler extends Handler
{
    protected string $endpoint;
    protected int $mask = EP_PERMALINK;
    /** @var null|callable(ModelInterface, string): mixed */
    protected $handler = null;
    protected ?string $template = null;
    protected bool $noIndex = false;
    protected bool $hideAdminBar = false;
    protected bool $acceptsValue = false;

    /**
     * Post types of each endpoint: the handlers of a same endpoint share it to tell a 404 apart
     *
     * @var array<string, string[]>
     */
    protected static array $postTypes = [];

    protected static bool $flushChecked = false;

    public function __construct(string $endpoint, ?callable $handler = null)
    {
        $this->setEndpoint($endpoint);

        if ($handler) {
            $this->setHandler($handler);
        }
    }

    public static function make(string $endpoint, ?callable $handler = null): static
    {
        return new static($endpoint, $handler);
    }

    /**
     * Alias of make()
     */
    public static function withEndpoint(string $endpoint, ?callable $handler = null): static
    {
        return static::make($endpoint, $handler);
    }

    public function setEndpoint(string $endpoint): self
    {
        $this->endpoint = $endpoint;
        return $this;
    }

    public function endpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * Runs on template_redirect when the endpoint is requested: fn (ModelInterface $model, string $value)
     */
    public function setHandler(callable $handler): self
    {
        $this->handler = $handler;
        return $this;
    }

    public function setMask(int $mask): self
    {
        $this->mask = $mask;
        return $this;
    }

    /**
     * Theme template of the endpoint (locate_template()), the single template otherwise
     */
    public function template(string $template): self
    {
        $this->template = $template;
        return $this;
    }

    /**
     * noindex robots meta on the endpoint (wp_robots_no_robots())
     */
    public function noIndex(bool $noIndex = true): self
    {
        $this->noIndex = $noIndex;
        return $this;
    }

    public function hideAdminBar(bool $hide = true): self
    {
        $this->hideAdminBar = $hide;
        return $this;
    }

    /**
     * Accept /{endpoint}/{value}/: the value is given to the callable (value()), a 404 otherwise
     */
    public function acceptsValue(bool $accepts = true): self
    {
        $this->acceptsValue = $accepts;
        return $this;
    }

    public function actions(): void
    {
        $postTypes = static::$postTypes[$this->endpoint] ?? [];
        static::$postTypes[$this->endpoint] = array_values(array_unique([...$postTypes, $this->builder->getName()]));

        // Schema declared on init: too late for an init hook
        if (\did_action('init')) {
            $this->registerEndpoint();
        } else {
            \add_action('init', [$this, 'registerEndpoint']);
        }
        \add_action('wp_loaded', [$this, 'flushIfMissing']);
        \add_filter('request', [$this, 'normalizeQueryVar']);
        \add_action('template_redirect', [$this, 'handleRequest'], 0);
        \add_filter('template_include', [$this, 'templateInclude'], 99);
        \add_filter('wp_robots', [$this, 'robots']);
        \add_filter('show_admin_bar', [$this, 'showAdminBar']);
    }

    public function freeze(): void
    {
        \remove_action('init', [$this, 'registerEndpoint']);
        \remove_action('wp_loaded', [$this, 'flushIfMissing']);
        \remove_filter('request', [$this, 'normalizeQueryVar']);
        \remove_action('template_redirect', [$this, 'handleRequest'], 0);
        \remove_filter('template_include', [$this, 'templateInclude'], 99);
        \remove_filter('wp_robots', [$this, 'robots']);
        \remove_filter('show_admin_bar', [$this, 'showAdminBar']);
    }

    public function registerEndpoint(): void
    {
        \add_rewrite_endpoint($this->endpoint, $this->mask);
    }

    /**
     * Rules generated before an endpoint was declared: flushed once per request at most
     * (rules not generated yet are left to WordPress)
     */
    public function flushIfMissing(): void
    {
        if (static::$flushChecked) {
            return;
        }
        static::$flushChecked = true;

        $rules = \get_option('rewrite_rules');
        if (!\is_array($rules) || !\get_option('permalink_structure')) {
            return;
        }

        $queries = implode("\n", $rules);
        foreach (array_keys(static::$postTypes) as $endpoint) {
            if (!str_contains($queries, '&' . $endpoint . '=$matches[')) {
                \flush_rewrite_rules(false);
                return;
            }
        }
    }

    /**
     * Requested without value ("" with pretty permalinks, "1" otherwise): 1, as before; any other value is kept
     */
    public function normalizeQueryVar(array $queryVars): array
    {
        if (isset($queryVars[$this->endpoint]) && \in_array($queryVars[$this->endpoint], ['', '1', 1], true)) {
            $queryVars[$this->endpoint] = 1;
        }

        return $queryVars;
    }

    /**
     * Value of the endpoint in the main query: null when not requested, "" without value
     */
    public function value(): ?string
    {
        $value = \get_query_var($this->endpoint, null);
        return match (true) {
            null === $value, false === $value => null,
            1 === $value => '',
            default => (string)$value,
        };
    }

    /**
     * The main query is the endpoint of a post of this post type
     */
    public function isRequested(): bool
    {
        $value = $this->value();
        return null !== $value && ('' === $value || $this->acceptsValue) && \is_singular($this->builder->getName());
    }

    public function handleRequest(): void
    {
        $value = $this->value();
        if (null === $value) {
            return;
        }

        if (!$this->isRequested()) {
            // Another post type declaring this endpoint handles it
            if (!\is_singular($this->builder->getName()) && \is_singular(static::$postTypes[$this->endpoint] ?? [])) {
                return;
            }
            $this->notFound();
            return;
        }

        if ($this->handler) {
            $model = $this->builder->model(\get_queried_object_id(), \get_queried_object());
            \call_user_func($this->handler, $model, $value);
        }
    }

    protected function notFound(): void
    {
        global $wp_query;

        $wp_query->set_404();
        \status_header(404);
        \nocache_headers();
    }

    public function templateInclude(string $template): string
    {
        if (!$this->template || !$this->isRequested()) {
            return $template;
        }

        return \locate_template($this->template) ?: $template;
    }

    public function robots(array $robots): array
    {
        return $this->noIndex && $this->isRequested() ? \wp_robots_no_robots($robots) : $robots;
    }

    public function showAdminBar(bool $show): bool
    {
        return $show && !($this->hideAdminBar && $this->isRequested());
    }

    /**
     * URL of the endpoint of a post, with a value when given
     */
    public function url(ModelInterface|int $model, string $value = ''): string
    {
        $permalink = (string)\get_permalink($model instanceof ModelInterface ? $model->id() : $model);

        if (!\get_option('permalink_structure')) {
            return \add_query_arg($this->endpoint, '' === $value ? 1 : $value, $permalink);
        }

        return \user_trailingslashit(\trailingslashit($permalink) . $this->endpoint . ('' === $value ? '' : '/' . rawurlencode($value)));
    }
}
