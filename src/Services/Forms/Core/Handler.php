<?php

namespace Coretik\Services\Forms\Core;

use Coretik\Services\Forms\Config;

class Handler
{
    protected $forms = [];
    protected static $instance = null;

    protected $config;

    public function __construct(ConfigInterface $config = null)
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

        foreach ($this->forms as $form) {
            if ($form instanceof \Closure) {
                $form = \call_user_func($form);
            }

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

                case $form instanceof Handlable:
                    $hook = defined($form::class . '::HOOK') ? $form::HOOK : 'template_redirect';
                    $priority = defined($form::class . '::PRIORITY') ? $form::PRIORITY : 10;
                    add_action($hook, function () use ($form) {
                        static::handleRequest($form);
                    }, $priority);
                    add_action('admin_init', function () use ($form) {
                        static::handleRequest($form);
                    });
                    break;
            }
        }
    }

    public function attach(Handlable $form)
    {
        $form->setConfigIfNotDefined($this->config);
        $this->forms[$form->id()] = $form;
    }

    public function factory(Handlable $form)
    {
        $form->setConfigIfNotDefined($this->config);
        $this->forms[$form->id()] = function () use ($form) {
            return clone $form;
        };
    }

    public function has(string $name): bool
    {
        return !empty($this->forms[$name]);
    }

    public function get(string $name): Handlable
    {
        $form = $this->forms[$name];
        if ($form instanceof \Closure) {
            $form = \call_user_func($form);
        }
        return $form;
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
