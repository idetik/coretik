<?php

namespace Coretik\Services\Email;

use Globalis\WP\Cubi;
use Pelago\Emogrifier\CssInliner;

use function Globalis\WP\Cubi\wp_mail_html;

class Email
{
    public $email_title;

    public $to;
    public $subject;
    public $message;
    public $headers = [];
    public $attachments;

    protected $templatesDir;
    protected static $css = [];

    protected $builder;

    public function __construct(Builder $builder, string $templates_dir)
    {
        $this->builder = $builder;
        $this->templatesDir = $templates_dir;
    }

    public function setAttachments(array $attachments)
    {
        $this->attachments = $attachments;
        return $this;
    }

    protected static function formatTo($to)
    {
        if (!\is_array($to)) {
            return $to;
        }

        if (!empty($recipient['name']) && !empty($recipient['email'])) {
            return sprintf('%s <%s>', $recipient['name'] ?? '', $recipient['email']);
        }

        $return = [];
        foreach ($to as $recipient) {
            if (is_array($recipient) && !empty($recipient['name']) && !empty($recipient['email'])) {
                $recipient = sprintf('%s <%s>', $recipient['name'] ?? '', $recipient['email']);
            }
            $return[] = $recipient['email'] ?? $recipient;
        }
        return $return;
    }

    public function setTo($to = [])
    {
        $this->to = static::formatTo($to);
        return $this;
    }

    public function setSubject(string $subject)
    {
        $this->subject = $subject;
        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject ?? '';
    }

    public function setTitle(string $title)
    {
        $this->email_title = $title;
        return $this;
    }

    public function setMessage(string $message)
    {
        $this->message = $message;
        return $this;
    }

    public function addHeader(string $header)
    {
        $this->headers = array_merge($this->headers, [$header]);
        return $this;
    }

    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
        return $this;
    }

    public function useTemplate(string $template, array $data = [])
    {
        $this->message = Cubi\include_template_part($this->templatesDir . '/' . $template, $data + ['email' => $this], true);
        return $this;
    }

    public function html()
    {
        return Cubi\include_template_part($this->builder->basePath(), ['email' => $this], true);
    }

    protected function css()
    {
        if (!empty(self::$css[$this->builder->stylesheetPath()])) {
            return self::$css[$this->builder->stylesheetPath()];
        }

        $path = $this->builder->stylesheetPath();

        if (empty($path) || !file_exists($path) || !is_readable($path)) {
            self::$css[$path] = false;
        } else {
            self::$css[$path] = file_get_contents($path);
        }

        return self::$css[$path];
    }

    protected function inline()
    {
        return CssInliner::fromHtml($this->html())
            ->inlineCss($this->css())
            ->render();
    }

    public function send()
    {
        if (empty($this->to)) {
            return false;
        }

        return wp_mail_html(
            $this->to,
            $this->getSubject(),
            $this->inline(),
            $this->headers,
            $this->attachments
        );
    }
}
