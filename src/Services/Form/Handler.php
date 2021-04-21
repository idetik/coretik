<?php

namespace Coretik\Services\Forms;

class Handler
{
    protected $forms = [];
    protected static $instance = null;

    protected function __construct() {}

    public static function instance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function hooks()
    {
        \add_action('init', [__CLASS__, 'init'], 99);
    }

    public static function init()
    {
        if (empty($_REQUEST)) {
            return;
        }

        foreach (static::instance()->forms as $form) {
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
                    add_action('template_redirect', function() use ($form) {
                        static::handleRequest($form);
                    });
                    add_action('admin_init', function() use ($form) {
                        static::handleRequest($form);
                    });
                    break;
            }
        }
    }

    public static function attach(Handlable $form)
    {
        static::instance()->forms[$form->getName()] = $form;
    }

    public static function factory(Handlable $form)
    {
        static::instance()->forms[$form->getName()] = function () use ($form) {
            return clone $form;
        };
    }

    public static function has(string $name): bool
    {
        return !empty(static::instance()->forms[$name]);
    }

    public static function get(string $name): Handlable
    {
        $form = static::instance()->forms[$name];
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

Handler::hooks();
