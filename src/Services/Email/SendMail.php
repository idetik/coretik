<?php

namespace Coretik\Services\Email;

use Coretik\Services\Queue\JobInterface;
use Coretik\Services\Queue\ShouldQueue;

/**
 * Background sending of a mail (Mailer::queue()), queue "mail": 3 attempts (1 then 2 minutes later), recorded once sent
 * or after the last failed attempt, with the user who asked for it.
 *
 * Sent by the "mailer" service of the app (filter coretik/mail/mailer to use another one).
 *
 * @phpstan-consistent-constructor
 */
class SendMail implements JobInterface, ShouldQueue
{
    public const QUEUE = 'mail';

    public function __construct(protected Mail&QueueableMail $mail, protected ?int $actor = null)
    {
    }

    public function mail(): Mail&QueueableMail
    {
        return $this->mail;
    }

    public function actor(): ?int
    {
        return $this->actor;
    }

    public function queue(): string
    {
        return static::QUEUE;
    }

    public function tries(): int
    {
        return 3;
    }

    public function backoff(int $attempts): int
    {
        return MINUTE_IN_SECONDS * max(1, $attempts);
    }

    public function handle(): void
    {
        // Nobody to send it to anymore (anonymized account…): nothing to do
        if (!Mailer::recipients($this->mail->to())) {
            return;
        }

        if (!$this->mailer()->deliver($this->mail, $this->actor, false)) {
            throw new \RuntimeException(sprintf('Email "%s" not sent.', $this->mail->label()));
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->mailer()->record($this->mail, false, $this->actor);
    }

    protected function mailer(): Mailer
    {
        $app = \coretik();
        $mailer = \apply_filters('coretik/mail/mailer', $app && $app->has('mailer') ? $app->get('mailer') : null, $this);
        if (!$mailer instanceof Mailer) {
            throw new \RuntimeException(sprintf('No %s: declare the "mailer" service of the app.', Mailer::class));
        }

        return $mailer;
    }

    public function toPayload(): array
    {
        return ['mail' => $this->mail::class, 'data' => $this->mail->toPayload(), 'actor' => $this->actor];
    }

    public static function fromPayload(array $payload): static
    {
        $class = (string)($payload['mail'] ?? '');
        if (!is_subclass_of($class, Mail::class) || !is_subclass_of($class, QueueableMail::class)) {
            throw new \UnexpectedValueException(sprintf('Unknown mail class "%s".', $class));
        }

        $mail = $class::fromPayload((array)($payload['data'] ?? []));
        if (!$mail) {
            throw new \UnexpectedValueException(sprintf('Mail %s: its object no longer exists.', $class));
        }

        return new static($mail, isset($payload['actor']) ? (int)$payload['actor'] : null);
    }
}
