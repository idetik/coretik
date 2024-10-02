<?php

namespace Coretik\Services\Modals;

use function Globalis\WP\Cubi\include_template_part;

class Modal implements ModalInterface
{
    protected bool $isOpen;
    protected string $title;
    protected $body;
    protected array $data;
    protected bool $keep = true;
    protected string $id;
    protected bool $closable = true;
    protected bool $large = false;
    protected string $template_file_modal;

    /**
     * @param string|callable $body - Template filename or print body content in callable
     * @param array $data - Data passed to body template or callable
     * @param bool $open - Set modal open
     * @param bool $template_file_modal - Template modal wrapper filename, with $body var to place inside
     */
    public function __construct(callable|string $body, array $data = [], bool $open = false, string $template_file_modal = '')
    {
        $this->body = $body;
        $this->data = $data;
        $this->isOpen = $open;
        $this->id = $data['id'] ?? (\is_string($body) ? \uniqid(\basename($body) . '-') : \uniqid());
        $this->template_file_modal = $template_file_modal;
    }

    /**
     * Summary of make
     * @param array $modalData
     * @throws \InvalidArgumentException
     * @return Modal
     */
    public static function make(array $modalData = []): static
    {
        if (!array_key_exists('body', $modalData)) {
            throw new \InvalidArgumentException('Modal body is required');
        }

        $modal = new static(
            $modalData['body'],
            $modalData['data'] ?? [],
            $modalData['open'] ?? false,
            $modalData['template_file_modal'] ?? ''
        );

        if (array_key_exists('id', $modalData)) {
            $modal->setId($modalData['id']);
        }

        if (array_key_exists('title', $modalData)) {
            $modal->setTitle($modalData['title']);
        }

        if (array_key_exists('closable', $modalData)) {
            $modal->setClosable($modalData['closable']);
        }

        if (array_key_exists('large', $modalData)) {
            $modal->setLarge($modalData['large']);
        }

        if (array_key_exists('keep', $modalData)) {
            $modal->setKeep($modalData['keep']);
        }

        return $modal;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function title(): string
    {
        return $this->title ?? '';
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function isOpen(): bool
    {
        return $this->isOpen;
    }

    public function open(bool $open = true): static
    {
        $this->isOpen = $open;
        return $this;
    }

    public function isClosable(): bool
    {
        return $this->closable;
    }

    public function setClosable(bool $closable = true): static
    {
        $this->closable = $closable;
        return $this;
    }

    public function isLarge(): bool
    {
        return $this->large;
    }

    public function setLarge(bool $large = true): static
    {
        $this->large = $large;
        return $this;
    }

    public function keep(): bool
    {
        return $this->keep;
    }

    public function setKeep(bool $keep = true): static
    {
        $this->keep = $keep;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'title' => $this->title(),
            'is_open' => $this->isOpen(),
            'is_closable' => $this->isClosable(),
            'is_large' => $this->isLarge(),
            'keep' => $this->keep(),
        ];
    }

    public function addTo($container): void
    {
        $container->add($this);
    }

    public function render(): void
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
        } else {
            echo $body;
        }
    }

    public function __sleep(): array
    {
        return ['body', 'data'];
    }
}
