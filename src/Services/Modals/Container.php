<?php

namespace Coretik\Services\Modals;

use SplObjectStorage;

class Container
{
    protected bool $hasOpen = false;
    protected SplObjectStorage $modals;
    protected static $scriptsLoaded = false;
    protected string $tpl_container = __DIR__ . '/views/container.php';
    protected string $tpl_modal = __DIR__ . '/views/modal.php';

    public function __construct(string $tpl_container = '', string $tpl_modal = '', bool $load_scripts = true)
    {
        $this->modals = new SplObjectStorage();

        if (\apply_filters('coretik/modals/load_scripts', $load_scripts)) {
            \add_action('admin_footer', [$this, 'scripts'], 99);
            // Themes have their own modals script (data-modal-trigger…): the coretik one would conflict
            if (\apply_filters('coretik/modals/load_front_scripts', false)) {
                \add_action('wp_footer', [$this, 'scripts'], 99);
            }
        }

        if (\apply_filters('coretik/modals/print_modals', $load_scripts)) {
            \add_action('admin_footer', [$this, 'modals']);
            \add_action('wp_footer', [$this, 'modals']);
        }

        $tpl_container = \apply_filters('coretik/modals/tpl_container', $tpl_container);
        $tpl_modal = \apply_filters('coretik/modals/tpl_modal', $tpl_modal);

        if (!empty($tpl_container)) {
            $this->tpl_container = locate_template($tpl_container . '.php');
        }
        if (!empty($tpl_modal)) {
            $this->tpl_modal = locate_template($tpl_modal . '.php');
        }
    }

    /**
     * @param string|callable $template
     */
    public function factory($template, array $data = [], bool $open = false, bool $add = true): ModalInterface
    {
        $modal = new Modal($template, $data, $open, $this->tpl_modal);
        if ($add) {
            $this->add($modal);
        }
        return $modal;
    }

    public function add(ModalInterface $modal)
    {
        if (!$this->modals->contains($modal)) {
            $this->modals->attach($modal);
        }
    }

    public function hasOpen()
    {
        foreach ($this->modals as $modal) {
            if ($modal->isOpen()) {
                return true;
            }
        }
        return false;
    }

    public function render()
    {
        foreach ($this->modals as $modal) {
            $modal->render();
        }
    }

    /**
     * @deprecated
     * @return void
     */
    public function modals(): void
    {
        include $this->tpl_container;
    }

    public function displayContainer(): void
    {
        include $this->tpl_container;
    }

    /**
     * Opens modals with [data-modal="{id}"] buttons, closes them with [data-modal-close], the backdrop or Escape.
     * In admin, also prints the modals styles.
     */
    public function scripts(): void
    {
        if (static::$scriptsLoaded) {
            return;
        }
        static::$scriptsLoaded = true;

        if (\is_admin()) {
            echo '<style>' . \file_get_contents(__DIR__ . '/admin.css') . '</style>';
        }
        echo '<script>' . \file_get_contents(__DIR__ . '/scripts.js') . '</script>';
    }
}
