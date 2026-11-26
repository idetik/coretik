<?php

namespace Coretik\Services\Forms\Core;

use Coretik\Services\Forms\Config;

/**
 * Registry of the application forms.
 *
 * Forms are registered as instances (built right away) or as class names (lazy): a lazy form is only
 * built when it is used, i.e. when its own AJAX action is requested or when it is fetched with get().
 * A lazy form class must define an ID constant, the form id. Its AJAX action is "form-{ID}", or the
 * AJAX_ACTION constant of the class when wpAjaxAction() is overridden.
 */
class Handler
{
    /** @var array<string, Handlable|\Closure> */
    protected $forms = [];

    /** @var array<string, array{class: class-string<Handlable>, singleton: bool, instance: ?Handlable}> */
    protected array $lazy = [];

    protected static $instance = null;

    protected $config;

    public function __construct(?ConfigInterface $config = null)
    {
        $this->config = $config ?? (new Config());
        $this->hooks();
    }

    public function hooks()
    {
        \add_action('init', [$this, 'init'], 99);
    }

    public function init()
    {
        if (empty($_REQUEST)) {
            return;
        }

        foreach ($this->lazy as $id => $entry) {
            if (\is_a($entry['class'], Asyncable::class, true)) {
                $this->listenLazyAsync($id, $entry['class']);
            } else {
                // A synchronous form checks itself whether it is submitted: it has to be built
                $this->listen($this->get($id));
            }
        }

        foreach ($this->forms as $form) {
            if ($form instanceof \Closure) {
                $form = \call_user_func($form);
            }

            $this->listen($form);
        }
    }

    protected function listen(Handlable $form): void
    {
        switch (true) {
            case $form instanceof Asyncable:
                add_action(sprintf('wp_ajax_%s', $form->wpAjaxAction()), function () use ($form) {
                    static::handleAsyncRequest($form);
                });

                if ($form->public()) {
                    add_action(sprintf('wp_ajax_nopriv_%s', $form->wpAjaxAction()), function () use ($form) {
                        static::handleAsyncRequest($form);
                    });
                }
                break;

            default:
                $hook = \defined($form::class . '::HOOK') ? \constant($form::class . '::HOOK') : 'template_redirect';
                $priority = \defined($form::class . '::PRIORITY') ? \constant($form::class . '::PRIORITY') : 10;
                add_action($hook, function () use ($form) {
                    static::handleRequest($form);
                }, $priority);
                add_action('admin_init', function () use ($form) {
                    static::handleRequest($form);
                });
                break;
        }
    }

    /**
     * Hooks the AJAX action of a lazy form: the form is only built when this action is requested.
     *
     * @param class-string<Asyncable> $class
     */
    protected function listenLazyAsync(string $id, string $class): void
    {
        $action = \defined($class . '::AJAX_ACTION') ? \constant($class . '::AJAX_ACTION') : 'form-' . $id;

        $handle = function (bool $public) use ($id, $action) {
            $form = $this->get($id);

            if (!$form instanceof Asyncable) {
                return;
            }

            if ($form->wpAjaxAction() !== $action) {
                \_doing_it_wrong(
                    $form::class . '::wpAjaxAction',
                    \sprintf('A lazy form overriding wpAjaxAction() must declare it in an AJAX_ACTION constant ("%s" expected).', $form->wpAjaxAction()),
                    '2.0.0'
                );
                return;
            }

            // Not public: nothing answers, as if the nopriv action was not registered
            if ($public && !$form->public()) {
                return;
            }

            static::handleAsyncRequest($form);
        };

        add_action('wp_ajax_' . $action, fn () => $handle(false));
        add_action('wp_ajax_nopriv_' . $action, fn () => $handle(true));
    }

    /**
     * Registers a form shared by every get(): an instance, or a class name to build it on first use.
     *
     * @param Handlable|class-string<Handlable> $form
     */
    public function attach(Handlable|string $form)
    {
        if (\is_string($form)) {
            $this->registerLazy($form, true);
            return;
        }

        $form->setConfigIfNotDefined($this->config);
        $this->forms[$form->id()] = $form;
    }

    /**
     * Registers a form returned as a fresh copy by each get(): an instance (cloned), or a class name
     * (built on first use, then cloned).
     *
     * @param Handlable|class-string<Handlable> $form
     */
    public function factory(Handlable|string $form)
    {
        if (\is_string($form)) {
            $this->registerLazy($form, false);
            return;
        }

        $form->setConfigIfNotDefined($this->config);
        $this->forms[$form->id()] = function () use ($form) {
            return clone $form;
        };
    }

    /**
     * @param class-string<Handlable> $class
     */
    protected function registerLazy(string $class, bool $singleton): void
    {
        if (!\is_a($class, Handlable::class, true)) {
            throw new \InvalidArgumentException(\sprintf('%s is not a form (%s).', $class, Handlable::class));
        }

        $id = \defined($class . '::ID') ? \constant($class . '::ID') : null;

        // Without ID constant, the id is only known once built: register the instance right away
        if (empty($id)) {
            $singleton ? $this->attach(new $class()) : $this->factory(new $class());
            return;
        }

        unset($this->forms[$id]);
        $this->lazy[$id] = ['class' => $class, 'singleton' => $singleton, 'instance' => null];
    }

    public function has(string $name): bool
    {
        return !empty($this->forms[$name]) || isset($this->lazy[$name]);
    }

    /**
     * Whether the form is built (always true for a form registered as an instance).
     */
    public function isLoaded(string $name): bool
    {
        return isset($this->forms[$name]) || null !== ($this->lazy[$name]['instance'] ?? null);
    }

    public function get(string $name): Handlable
    {
        if (isset($this->lazy[$name])) {
            return $this->getLazy($name);
        }

        if (!isset($this->forms[$name])) {
            throw new \OutOfBoundsException(\sprintf('Unknown form "%s".', $name));
        }

        $form = $this->forms[$name];
        if ($form instanceof \Closure) {
            $form = \call_user_func($form);
        }
        return $form;
    }

    protected function getLazy(string $name): Handlable
    {
        $entry = &$this->lazy[$name];

        if (null === $entry['instance']) {
            $form = new $entry['class']();
            $form->setConfigIfNotDefined($this->config);

            if ((string) $form->id() !== (string) $name) {
                throw new \LogicException(\sprintf('%s::ID is "%s" but the form id is "%s".', $entry['class'], $name, $form->id()));
            }

            $entry['instance'] = $form;
        }

        return $entry['singleton'] ? $entry['instance'] : clone $entry['instance'];
    }

    public static function handleRequest(Handlable $form)
    {
        if ($form->isRunnable()) {
            $form->process();
        }
    }

    public static function handleAsyncRequest(Asyncable $form)
    {
        if ($form->isRunnable()) {
            $form->process();
            $form->view();
            exit;
        }
    }
}
