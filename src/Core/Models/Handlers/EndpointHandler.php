<?php

namespace Coretik\Core\Models\Handlers;

use Coretik\Core\Builders\Handler;

class EndpointHandler extends Handler
{
    private string $endpoint;
    private int $mask = EP_PERMALINK;
    private $handler;

    public function __construct(string $endpoint, ?callable $handler = null)
    {
        $this->setEndpoint($endpoint);

        if ($handler) {
            $this->setHandler($handler);
        }
    }

    public static function withEndpoint(string $endpoint, ?callable $handler = null): self
    {
        return new static($endpoint, $handler);
    }

    public function setEndpoint(string $endpoint): self
    {
        $this->endpoint = $endpoint;
        return $this;
    }

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

    public function actions(): void
    {
        \add_action('init', [$this, 'registerEndpoint']);
        \add_action('template_redirect', [$this, 'handleRequest'], 0);
        \add_filter('request', [$this, 'forceQueryVarValue']);
    }

    public function freeze(): void
    {
        \remove_action('init', [$this, 'registerEndpoint']);
        \remove_action('template_redirect', [$this, 'handleRequest'], 0);
        \remove_filter('request', [$this, 'forceQueryVarValue']);
    }

    public function registerEndpoint(): void
    {
        \add_rewrite_endpoint($this->endpoint, $this->mask);
    }

    public function forceQueryVarValue($query_vars): array
    {
        if (isset($query_vars[$this->endpoint])) {
            if ('' === $query_vars[$this->endpoint]) {
                $query_vars[$this->endpoint] = 1;
                add_filter('show_admin_bar', fn () => false);
            } else {
                $query_vars[$this->endpoint] = 0;
            }
        }

        return $query_vars;
    }

    public function handleRequest()
    {
        if (!\is_singular($this->builder->getName())) {
            return;
        }

        if (1 !== get_query_var($this->endpoint)) {
            return;
        }

        global $post;

        $model = $this->builder->model(get_the_ID(), $post);
        \call_user_func($this->handler, $model);
    }
}
