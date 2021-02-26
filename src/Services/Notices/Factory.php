<?php

namespace Coretik\Services\Notices;

class Factory
{
    protected $container;

    public function __construct($container)
    {
        $this->container = $container;
    }

    public function success(string $message)
    {
        $this->container[] = new NoticeSuccess($message);
    }

    public function error(string $message)
    {
        $this->container[] = new NoticeError($message);
    }

    public function notice(string $message, callable $displayer)
    {
        $this->container[] = new Notice($message, $displayer);
    }
}
