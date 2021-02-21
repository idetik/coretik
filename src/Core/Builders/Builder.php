<?php

namespace Coretik\Core\Builders;

use Coretik\Core\Builders\Interfaces\BuilderInterface;
use Coretik\Core\Builders\Interfaces\HandlerInterface;
use Coretik\Core\Collection;

abstract class Builder implements BuilderInterface
{
    protected $registerPriority = 0;
    protected $services;
    protected $handlers;

    abstract public function getName(): string;
    abstract public function getType(): string;

    public function __construct()
    {
        $this->services = new Collection();
        $this->handlers = new \SplObjectStorage();
    }

    public function priority(): int
    {
        return $this->registerPriority;
    }

    public function attach(string $name, callable $service)
    {
        $this->services[$name] = $service;
    }

    public function handler(HandlerInterface $handler): void
    {
        $this->handlers->attach($handler);
    }

    public function handlers(array $handlers): void
    {
        foreach ($handlers as $handler) {
            $this->handler($handler);
        }
    }

    public function runHandlers(): void
    {
        foreach ($this->handlers as $handler) {
            $handler->handle($this);
        }
    }

    public function removeHandlers()
    {
        foreach ($this->handlers as $handler) {
            $handler->freeze();
        }
    }

    public function getHandlers()
    {
        return $this->handlers;
    }

    public function __call($method, $args = [])
    {
        if ($this->services->has($method)) {
            return \call_user_func($this->services->get($method), $args);
        }
    }
}
