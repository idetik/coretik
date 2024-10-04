<?php

namespace Coretik\Core\Builders;

use Coretik\Core\Builders\Interfaces\BuilderInterface;
use Coretik\Core\Builders\Interfaces\HandlerInterface;
use Coretik\Core\Builders\Traits\Maker;
use Coretik\Core\Collection;
use Exception;

abstract class Builder implements BuilderInterface
{
    use Maker;

    protected $registerPriority = 0;
    protected $services;
    protected $handlers;
    protected $handlersClassName = [];

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

    public function attach(string $name, callable $service): self
    {
        $this->services[$name] = $service;
        return $this;
    }

    public function handler(string|HandlerInterface $handler): self
    {
        if (\is_string($handler)) {
            if (!\class_exists($handler) || !in_array(HandlerInterface::class, \class_implements($handler))) {
                throw new Exception('Undefined Handler : ' . $handler);
            }
            $handler = new $handler();
        }
        $this->handlersClassName[] = $handler::class;
        $this->handlers->attach($handler);
        return $this;
    }

    public function hasHandlerClassName(string $classname): bool
    {
        return \in_array($classname, $this->handlersClassName);
    }

    public function handlers(array $handlers): self
    {
        foreach ($handlers as $handler) {
            $this->handler($handler);
        }
        return $this;
    }

    public function runHandlers(): self
    {
        foreach ($this->handlers as $handler) {
            $handler->handle($this);
        }
        return $this;
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
            return \call_user_func($this->services->get($method), ...$args);
        }
    }
}
