<?php

namespace Coretik\Services\Modals;

class Container
{
    protected $hasOpen = false;
    protected $modals;
    protected static $scriptsLoaded = false;
    protected $tpl_container = __DIR__ . '/views/container.php';
    protected $tpl_modal = __DIR__ . '/views/modal.php';

    public function __construct(string $tpl_container = '', string $tpl_modal = '', bool $load_scripts = true)
    {
        $this->modals = new \SplObjectStorage();

        if ($load_scripts) {
            \add_action('admin_footer', [$this, 'modals']);
            \add_action('wp_footer', [$this, 'modals']);
        }

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
    public function factory($template, array $data = [], bool $open = false)
    {
        $modal = new Modal($template, $data, $open, $this->tpl_modal);
        $this->add($modal);
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

    public function modals()
    {
        include $this->tpl_container;

        if (!static::$scriptsLoaded) {
            ?>
            <script>
                <?php
                include 'scripts.js';
                ?>
            </script>
            <?php
        }
    }
}
