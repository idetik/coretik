<?php

namespace Coretik\Services\Modals;

use function Globalis\WP\Cubi\include_template_part;

class Modal implements ModalInterface
{
    protected $isOpen;
    protected $body;
    protected $data;
    protected $id;
    protected $closable = true;
    protected $large = false;
    protected $template_file_modal;

    /**
     * @param string|callable $body - Template filename or print body content in callable 
     * @param array $data - Data passed to body template or callable
     * @param bool $open - Set modal open
     * @param bool $template_file_modal - Template modal wrapper filename, with $body var to place inside
     */
    public function __construct(callable|string $body, array $data, bool $open = false, string $template_file_modal = '')
    {
        $this->body = $body;
        $this->data = $data;
        $this->isOpen = $open;
        $this->id = $data['id'] ?? (\is_string($body) ? \uniqid(\basename($body) . '-') : \uniqid());
        $this->template_file_modal = $template_file_modal;
    }

    public function id()
    {
        return $this->id;
    }

    public function setId(string $id)
    {
        $this->id = $id;
    }

    public function isOpen()
    {
        return $this->isOpen;
    }

    public function open(bool $open = true)
    {
        $this->isOpen = $open;
        return $this;
    }

    public function isClosable(): bool
    {
        return $this->closable;
    }

    public function setClosable(bool $closable = true)
    {
        $this->closable = $closable;
        return $this;
    }

    public function isLarge(): bool
    {
        return $this->large;
    }

    public function toArray()
    {
        return [
            'id' => $this->id(),
            'is_open' => $this->isOpen(),
            'is_closable' => $this->isClosable(),
            'is_large' => $this->isLarge(),
        ];
    }

    public function render()
    {
        $data = $this->data + $this->toArray();
        \ob_start();
        if (\is_callable($this->body)) {
            \call_user_func($this->body, $data);
        } else {
            include_template_part($this->body, $data);
        }
        $body = \ob_get_clean();

        if (!empty($this->template_file_modal) && \file_exists($this->template_file_modal)) {
            \ob_start();
            extract($data);
            include $this->template_file_modal;
            \ob_end_flush();
        }
    }

    public function __sleep()
    {
        return ['body', 'data'];
    }
}
