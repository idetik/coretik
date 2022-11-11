<?php

namespace Coretik\Services\Notices;

class Notice
{
    protected $message;
    protected $displayer;
    protected $completed;

    public function __construct(string $message, ?callable $displayer = null)
    {
        $this->message = $message;
        $this->displayer = $displayer ?? 'print';
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

    public function setCompleted()
    {
        $this->completed = true;
        return $this;
    }

    public function __toString()
    {
        return $this->message;
    }
}
