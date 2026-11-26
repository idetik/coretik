<?php

namespace Coretik\Tests\Unit\Email;

use Coretik\Services\Email\Email;
use Coretik\Services\Email\Mail;
use Coretik\Services\Email\QueueableMail;

/**
 * Mail of the tests: recipients and copies given, records its deliveries
 */
class TestMail extends Mail implements QueueableMail
{
    /** @var array<array{key: string, sent: bool, actor: ?int}> */
    public static array $delivered = [];

    public function __construct(
        public string|array $to = 'pierre@example.org',
        public string $subject = 'Hello',
        public array $cc = [],
        public array $bcc = [],
        public string|array|null $reply = null,
    ) {
    }

    public static function reset(): void
    {
        static::$delivered = [];
    }

    public function key(): string
    {
        return 'test.hello';
    }

    public function to(): string|array
    {
        return $this->to;
    }

    public function subject(): string
    {
        return $this->subject;
    }

    protected function template(): string
    {
        return 'test/hello';
    }

    public function title(): string
    {
        return 'Title';
    }

    public function data(): array
    {
        return ['name' => 'Pierre'];
    }

    public function cc(): array
    {
        return $this->cc;
    }

    public function bcc(): array
    {
        return $this->bcc;
    }

    public function replyTo(): string|array|null
    {
        return $this->reply;
    }

    public function attachments(): array
    {
        return ['/tmp/invoice.pdf'];
    }

    public function headers(): array
    {
        return ['X-Mail: test.hello'];
    }

    public function configure(Email $email): void
    {
        $email->addHeader('X-Configured: 1');
    }

    public function delivered(bool $sent, ?int $actor = null): void
    {
        static::$delivered[] = ['key' => $this->key(), 'sent' => $sent, 'actor' => $actor];
    }

    public function toPayload(): array
    {
        return ['to' => $this->to, 'subject' => $this->subject];
    }

    public static function fromPayload(array $payload): ?static
    {
        return empty($payload['subject']) ? null : new static($payload['to'], $payload['subject']);
    }
}
