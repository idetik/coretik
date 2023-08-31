<?php

namespace Coretik\Core\Builders;

use Coretik\Core\Builders\Traits\Registrable;
use Coretik\Core\Builders\Interfaces\{
    BuilderInterface,
    RegistrableInterface
};
use SplObjectStorage;

final class Status implements BuilderInterface, RegistrableInterface
{
    use Registrable;

    protected $status;
    protected $args;
    protected $registerPriority = 0;
    protected $handlers;

    public function __construct(string $status, array $args = [])
    {
        $this->status = $status;
        $default = [
            'label'                     => false,
            'label_count'               => false,
            'exclude_from_search'       => null,
            'public'                    => null,
            'internal'                  => null,
            'protected'                 => null,
            'private'                   => null,
            'publicly_queryable'        => null,
            'show_in_admin_status_list' => null,
            'show_in_admin_all_list'    => null,
            'date_floating'             => null,
        ];

        $this->setArgs($args);

        $this->handlers = new SplObjectStorage();
    }

    public function getType(): string
    {
        return 'status';
    }

    public function priority(): int
    {
        return $this->registerPriority;
    }

    public function getName(): string
    {
        return $this->status;
    }

    public function args()
    {
        return $this->args;
    }

    public function setArgs(array $args = []): self
    {
        $this->args = new Collection($args);
        return $this;
    }

    public function registerAction(): void
    {
        \register_post_status($this->status, $this->args->all());
    }

    public function handler(callable $handler): void
    {
        $this->handlers->attach($handler);
    }

    public function runHandlers(): void
    {
        foreach ($this->handlers as $handler) {
            $handler($this);
        }
    }
}
