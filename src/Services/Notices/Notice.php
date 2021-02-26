<?php

namespace Coretik\Services\Notices;

class Notice
{
    protected $message;
    protected $displayer;
    protected $completed;

    public function __construct(string $message, callable $displayer)
    {
        $this->message = $message;
        $this->displayer = $displayer;
        $this->completed = false;
    }

    public function waiting(): bool
    {
        return !$this->completed;
    }

    public function display()
    {
        \call_user_func($this->displayer, $this);
        $this->completed = true;
    }

    public function __toString()
    {
        return $this->message;
    }
}
