<?php

namespace Coretik\Services\Email;

use Coretik\Services\Email\Viewer\Catalog;
use Coretik\Services\Email\Viewer\MailCatalog;
use Coretik\Services\Email\Viewer\Viewer;
use Coretik\Services\Queue\Queue;

/**
 * Sends the emails of the application (Mail) in the layout of the Email service, now or in the background (Queue).
 *
 * $container['email'] = $container->factory(fn ($c) => new Email($c->get('email.builder'), 'templates/emails'));
 * $container['mailer'] = fn ($c) => (new Mailer(fn () => $c->get('email')))
 *     ->from('noreply@example.org', 'My site')          // Optional: noreply@{domain}, site name by default
 *     ->replyTo(['name' => 'Support', 'email' => 'support@example.org']);   // Optional
 *
 * coretik()->mailer()->send(new OrderConfirmation($order));             // Now
 * coretik()->mailer()->queue(new Newsletter($userId), 'newsletter-42'); // Background (QueueableMail)
 *
 * App::init() calls hooks() (filter coretik/app/init/mailer): WordPress default sender replaced, text version and
 * envelope sender of the HTML emails (deliverability), emails viewer when one is declared (viewer()).
 *
 * Hooks: coretik/mail/headers (filter: headers, Mail), coretik/mail/sent and coretik/mail/failed (actions: Mail,
 * Email|null, actor id|null), coretik/mail/queue (filter: Queue|null, Mailer)
 */
class Mailer
{
    /** @var callable(): Email */
    protected $email;

    protected ?string $fromEmail = null;
    protected ?string $fromName = null;

    /** @var string|array{name?: string, email: string}|null */
    protected string|array|null $replyTo = null;

    /** @var Queue|callable(): ?Queue|false|null null: the "queue" service of the app when declared, false: never */
    protected $queue = null;

    protected bool $deliverability = true;

    /** @var Catalog|callable(MailCatalog): mixed|null */
    protected $catalog = null;
    protected array $viewerOptions = [];

    protected bool $hooked = false;

    /**
     * @param callable(): Email $email A new Email for each sending (recipients, headers and attachments must not leak)
     */
    public function __construct(callable $email)
    {
        $this->email = $email;
    }

    /**
     * Sender of the emails, noreply@{domain of the site} and the site name by default
     */
    public function from(?string $email, ?string $name = null): static
    {
        $this->fromEmail = $email;
        $this->fromName = $name;
        return $this;
    }

    /**
     * Default reply address (Mail::replyTo() overrides it), none by default
     *
     * @param string|array{name?: string, email: string}|null $address
     */
    public function replyTo(string|array|null $address): static
    {
        $this->replyTo = $address;
        return $this;
    }

    /**
     * Queue of the background sendings: the "queue" service of the app by default (null), false to always send now
     *
     * @param Queue|callable(): ?Queue|false|null $queue
     */
    public function useQueue(Queue|callable|false|null $queue): static
    {
        $this->queue = $queue;
        return $this;
    }

    /**
     * Text version (multipart/alternative) and envelope sender (Return-Path = From) of the HTML emails, on by default
     */
    public function deliverability(bool $enabled = true): static
    {
        $this->deliverability = $enabled;
        return $this;
    }

    /**
     * Emails viewer of the back-office, registered by hooks(): a Catalog, or a callable filling a MailCatalog
     *
     * $mailer->viewer(fn (MailCatalog $catalog) => $catalog->add('order.confirmation', 'Confirmation', fn ($context) => …));
     *
     * @param Catalog|callable(MailCatalog): mixed $catalog
     * @param array<string, mixed> $options Viewer::DEFAULTS (slug, title, capability, parent…)
     */
    public function viewer(Catalog|callable $catalog, array $options = []): static
    {
        $this->catalog = $catalog;
        $this->viewerOptions = $options;
        return $this;
    }

    public function catalog(): ?Catalog
    {
        if (null === $this->catalog || $this->catalog instanceof Catalog) {
            return $this->catalog;
        }

        $catalog = new MailCatalog($this);
        ($this->catalog)($catalog);
        return $this->catalog = $catalog;
    }

    public function hooks(): void
    {
        if ($this->hooked) {
            return;
        }
        $this->hooked = true;

        // WordPress default sender only (wordpress@…): an explicit From header is kept
        \add_filter('wp_mail_from', fn ($from) => str_starts_with((string)$from, 'wordpress@') ? $this->fromEmail() : $from);
        \add_filter('wp_mail_from_name', fn ($name) => 'WordPress' === $name ? $this->fromName() : $name);

        if ($this->deliverability) {
            // After the SMTP configuration of the project (priority 10)
            \add_action('phpmailer_init', [$this, 'phpmailerInit'], 20);
        }

        if (\is_admin() && ($catalog = $this->catalog())) {
            (new Viewer($catalog, $this->viewerOptions))->hooks();
        }
    }

    /**
     * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer
     */
    public function phpmailerInit($phpmailer): void
    {
        if (\is_email($phpmailer->From)) {
            $phpmailer->Sender = $phpmailer->From;
        }

        if ('text/html' === $phpmailer->ContentType && '' === trim((string)$phpmailer->AltBody)) {
            $phpmailer->AltBody = static::plainText((string)$phpmailer->Body);
        }
    }

    public function fromEmail(): string
    {
        if ($this->fromEmail && \is_email($this->fromEmail)) {
            return $this->fromEmail;
        }

        $host = (string)\wp_parse_url(\home_url(), PHP_URL_HOST);
        return 'noreply@' . preg_replace('/^www\./', '', $host);
    }

    public function fromName(): string
    {
        return $this->fromName ?: \wp_specialchars_decode((string)\get_bloginfo('name'), ENT_QUOTES);
    }

    /**
     * Headers of a mail: From, Reply-To, Cc, Bcc then Mail::headers(). Without mail: From and the default Reply-To.
     *
     * @return string[]
     */
    public function headers(?Mail $mail = null): array
    {
        $headers = [sprintf('From: %s', static::address(['name' => $this->fromName(), 'email' => $this->fromEmail()]))];

        $replyTo = static::recipients($mail?->replyTo() ?? $this->replyTo ?? []);
        if ($replyTo) {
            $headers[] = 'Reply-To: ' . static::address($replyTo[0]);
        }

        if ($mail) {
            foreach (['Cc' => $mail->cc(), 'Bcc' => $mail->bcc()] as $name => $recipients) {
                foreach (static::recipients($recipients) as $recipient) {
                    $headers[] = sprintf('%s: %s', $name, static::address($recipient));
                }
            }
            $headers = array_merge($headers, $mail->headers());
        }

        return (array)\apply_filters('coretik/mail/headers', $headers, $mail);
    }

    /**
     * Email ready to send: recipients, subject, headers, attachments, body template in the layout
     */
    public function compose(Mail $mail): Email
    {
        $email = ($this->email)();
        if (!$email instanceof Email) {
            throw new \UnexpectedValueException(sprintf('The email factory of %s must return a %s.', static::class, Email::class));
        }

        $to = array_map([static::class, 'address'], static::recipients($mail->to()));

        $email->setTo(1 === count($to) ? $to[0] : $to)
            ->setSubject($mail->subject())
            ->setTitle($mail->title())
            ->setHeaders($this->headers($mail))
            ->setAttachments($mail->attachments())
            ->useTemplate($mail->templateName(), $mail->data());

        $mail->configure($email);

        return $email;
    }

    /**
     * Full HTML, CSS inlined (previews, WordPress core emails)
     */
    public function render(Mail $mail): string
    {
        return $this->compose($mail)->render();
    }

    /**
     * Sends now, the current user recorded as the actor
     */
    public function send(Mail $mail): bool
    {
        return $this->deliver($mail);
    }

    /**
     * Sends in the background with the queue: retried when the sending fails, recorded once sent or after the last
     * attempt. Sent now (and recorded) without queue, or when the queue runs in sync mode.
     *
     * @param string|null $group batch of emails, to follow it: coretik()->queue()->stats($group)
     * @return int|null job id, null when sent now
     */
    public function queue(Mail&QueueableMail $mail, ?string $group = null, int $delay = 0): ?int
    {
        $queue = $this->resolveQueue();
        if (!$queue || $queue->isSync()) {
            $this->deliver($mail);
            return null;
        }

        return $queue->push(new SendMail($mail, \get_current_user_id() ?: null), $delay, $group);
    }

    /**
     * Queue of the background sendings, null when there is none
     */
    public function resolveQueue(): ?Queue
    {
        $queue = $this->queue;
        if (false === $queue) {
            return null;
        }

        if (null === $queue) {
            $app = \coretik();
            $queue = $app && $app->has('queue') ? $app->get('queue') : null;
        } elseif (!$queue instanceof Queue) {
            $queue = $queue();
        }

        $queue = \apply_filters('coretik/mail/queue', $queue instanceof Queue ? $queue : null, $this);
        return $queue instanceof Queue ? $queue : null;
    }

    /**
     * Sends and records the sending. No recipient: nothing is sent nor recorded.
     *
     * @param int|null $actor user recorded, the current one by default
     * @param bool $recordFailure false: a failure is not recorded (attempt retried by the queue)
     */
    public function deliver(Mail $mail, ?int $actor = null, bool $recordFailure = true): bool
    {
        if (!static::recipients($mail->to())) {
            return false;
        }

        $email = $this->compose($mail);
        $sent = (bool)$email->send();

        if ($sent || $recordFailure) {
            $this->record($mail, $sent, $actor ?? (\get_current_user_id() ?: null), $email);
        }

        return $sent;
    }

    /**
     * Mail::delivered() then coretik/mail/sent or coretik/mail/failed ($email null after a queued sending failed)
     */
    public function record(Mail $mail, bool $sent, ?int $actor = null, ?Email $email = null): void
    {
        $mail->delivered($sent, $actor);
        \do_action($sent ? 'coretik/mail/sent' : 'coretik/mail/failed', $mail, $email, $actor);
    }

    /**
     * Test sending to $to only: no copies, subject prefixed, nothing recorded (emails viewer)
     */
    public function test(Mail $mail, string $to, string $prefix = '[Test] '): bool
    {
        $email = $this->compose($mail);
        $email->setTo($to)
            ->setSubject($prefix . $email->getSubject())
            ->setHeaders(array_values(array_filter(
                (array)$email->headers,
                fn ($header) => !preg_match('/^\s*b?cc\s*:/i', (string)$header)
            )));

        return (bool)$email->send();
    }

    /**
     * A WordPress core email (filters retrieve_password_notification_email, wp_new_user_notification_email,
     * password_change_email…) replaced by a mail: subject, HTML message and headers; WordPress keeps its recipient.
     *
     * add_filter('password_change_email', fn ($email, $user) => coretik()->mailer()->coreEmail($email, new PasswordChanged($user)), 10, 2);
     *
     * @param array{to?: string, subject?: string, message?: string, headers?: string|array} $email
     * @return array{to?: string, subject: string, message: string, headers: string[]}
     */
    public function coreEmail(array $email, Mail $mail): array
    {
        $email['subject'] = $mail->subject();
        $email['message'] = $this->render($mail);
        $email['headers'] = array_merge(
            ['Content-Type: text/html; charset=' . \get_bloginfo('charset')],
            $this->headers()
        );

        return $email;
    }

    /**
     * Recipients as a list of ['name' => …, 'email' => …], names safe in a header, invalid entries dropped.
     * Accepts an address, ['name' => …, 'email' => …], "Name <address>", or a list of them.
     *
     * @return array<array{name: string, email: string}>
     */
    public static function recipients(mixed $recipients): array
    {
        if (empty($recipients)) {
            return [];
        }

        if (!is_array($recipients) || isset($recipients['email'])) {
            $recipients = [$recipients];
        }

        $list = [];
        foreach ($recipients as $recipient) {
            if (is_string($recipient)) {
                $recipient = preg_match('/^(.*)<([^>]+)>\s*$/', $recipient, $matches)
                    ? ['name' => trim($matches[1], " \t\""), 'email' => $matches[2]]
                    : ['name' => '', 'email' => $recipient];
            }

            $email = is_array($recipient) ? trim((string)($recipient['email'] ?? '')) : '';
            if ('' === $email || preg_match('/[\r\n,;<>\s]/', $email)) {
                continue;
            }

            $list[] = ['name' => static::headerName((string)($recipient['name'] ?? '')), 'email' => $email];
        }

        return $list;
    }

    /**
     * "Name <address>", or the address alone
     *
     * @param array{name?: string, email: string} $recipient
     */
    public static function address(array $recipient): string
    {
        $name = static::headerName((string)($recipient['name'] ?? ''));
        return '' !== $name ? sprintf('%s <%s>', $name, $recipient['email']) : $recipient['email'];
    }

    /**
     * Recipients for the back-office: "Pierre Dupont <p@…> (copie : Marie Dupont <m@…>)", $none without recipient
     */
    public static function recipientLabel(Mail $mail, string $none = '', string $copies = ' (copie : %s)'): string
    {
        $to = static::recipients($mail->to());
        if (!$to) {
            return $none;
        }

        $cc = array_map([static::class, 'address'], static::recipients($mail->cc()));
        return implode(', ', array_map([static::class, 'address'], $to)) . ($cc ? sprintf($copies, implode(', ', $cc)) : '');
    }

    /**
     * Display name safe in an address header: no line break nor address delimiter
     */
    public static function headerName(string $name): string
    {
        return trim((string)preg_replace(['/[\r\n,;<>"]+/', '/\s+/'], ' ', $name));
    }

    /**
     * Text version of an HTML email: links as "label (url)", one line per block, preheader and styles dropped
     */
    public static function plainText(string $html): string
    {
        $html = (string)preg_replace([
            '#<(head|style|script)\b[^>]*>.*?</\1>#is',
            '#<(div|span)[^>]*class="[^"]*preheader[^"]*"[^>]*>.*?</\1>#is',
        ], '', $html);
        // Layout whitespace of the HTML: the lines come from the blocks below
        $html = (string)preg_replace('/\s+/u', ' ', $html);
        $html = (string)preg_replace('#<img\b[^>]*\balt="([^"]*)"[^>]*>#i', '$1', $html);
        $html = (string)preg_replace_callback('#<a\b[^>]*href="([^"]+)"[^>]*>(.*?)</a>#is', function ($matches) {
            $label = trim(\wp_strip_all_tags($matches[2]));
            $url = \html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return '' === $label || $label === $url || str_starts_with($url, 'mailto:') ? ($label ?: $url) : sprintf('%s (%s)', $label, $url);
        }, $html);
        $html = (string)preg_replace('#<br\s*/?>|</(p|div|h[1-6]|li|td|tr|table)>#i', "\n", $html);
        $html = (string)preg_replace('#<li\b[^>]*>#i', '- ', $html);

        $text = \html_entity_decode(\wp_strip_all_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string)preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
        $text = (string)preg_replace('/ *\n */', "\n", $text);

        return trim((string)preg_replace('/\n{3,}/', "\n\n", $text));
    }
}
