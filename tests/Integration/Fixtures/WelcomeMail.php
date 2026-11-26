<?php

namespace Coretik\Tests\Integration\Fixtures;

use Coretik\Services\Email\Email;
use Coretik\Services\Email\Mail;
use Coretik\Services\Email\QueueableMail;

/**
 * Mail to a user, copy to the address of the meta "second_email", history in the meta "mail_log"
 */
class WelcomeMail extends Mail implements QueueableMail
{
    public function __construct(protected \WP_User $user)
    {
    }

    public function key(): string
    {
        return 'account.welcome';
    }

    public function to(): string|array
    {
        return ['name' => $this->user->display_name, 'email' => $this->user->user_email];
    }

    public function cc(): array
    {
        $copy = (string)get_user_meta($this->user->ID, 'second_email', true);
        return $copy ? [$copy] : [];
    }

    public function subject(): string
    {
        return 'Bienvenue';
    }

    public function title(): string
    {
        return 'Votre compte';
    }

    protected function template(): string
    {
        return 'account/welcome';
    }

    public function data(): array
    {
        return ['name' => $this->user->first_name, 'url' => home_url('/compte/')];
    }

    public function configure(Email $email): void
    {
        if ($email instanceof FixtureEmail) {
            $email->preheader = 'Votre espace est prêt';
        }
    }

    public function delivered(bool $sent, ?int $actor = null): void
    {
        add_user_meta($this->user->ID, 'mail_log', ['mail' => $this->key(), 'sent' => $sent, 'actor' => $actor]);
    }

    public function toPayload(): array
    {
        return ['user' => $this->user->ID];
    }

    public static function fromPayload(array $payload): ?static
    {
        $user = get_userdata((int)($payload['user'] ?? 0));
        return $user ? new static($user) : null;
    }
}
