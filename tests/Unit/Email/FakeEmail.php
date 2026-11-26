<?php

namespace Coretik\Tests\Unit\Email;

use Coretik\Services\Email\Builder;
use Coretik\Services\Email\Email;

/**
 * Email without WordPress templates: the body lists the template and its data, send() records the email
 */
class FakeEmail extends Email
{
    /** @var FakeEmail[] */
    public static array $sent = [];
    public static bool $result = true;
    public string $template = '';
    public array $data = [];

    public function __construct()
    {
        parent::__construct(new Builder(), 'emails');
    }

    public static function reset(): void
    {
        static::$sent = [];
        static::$result = true;
    }

    public function useTemplate(string $template, array $data = [])
    {
        $this->template = $template;
        $this->data = $data;
        $this->message = sprintf('<p>%s</p>', $template);
        return $this;
    }

    public function render(): string
    {
        return sprintf('<h1>%s</h1>%s', $this->email_title, $this->message);
    }

    public function send()
    {
        if (empty($this->to)) {
            return false;
        }

        static::$sent[] = $this;
        return static::$result;
    }
}
