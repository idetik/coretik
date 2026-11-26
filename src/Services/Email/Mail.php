<?php

namespace Coretik\Services\Email;

/**
 * An email of the application: recipients, subject and body template, sent by the Mailer.
 * Projects extend it once per message (password reset, order confirmation…):
 *
 * class OrderConfirmation extends Mail
 * {
 *     public function __construct(protected Order $order) {}
 *
 *     public function key(): string { return 'order.confirmation'; }
 *     public function to(): string|array { return ['name' => $this->order->customerName(), 'email' => $this->order->email]; }
 *     public function subject(): string { return sprintf('Commande n° %s', $this->order->number); }
 *     protected function template(): string { return 'order/confirmation'; }
 *     public function data(): array { return ['order' => $this->order]; }
 * }
 *
 * coretik()->mailer()->send(new OrderConfirmation($order));
 *
 * The template (under the templates directory of the Email service) receives data() and $email (the Email being built).
 * A recipient is an address, ['name' => …, 'email' => …], or a list of them.
 */
abstract class Mail
{
    /**
     * Identifier for hooks, logs and the emails viewer: account.password_reset, order.confirmation…
     */
    abstract public function key(): string;

    /**
     * Main recipient(s), empty when there is nobody to send it to (nothing is sent)
     *
     * @return string|array{name?: string, email: string}|array<string|array{name?: string, email: string}>
     */
    abstract public function to(): string|array;

    abstract public function subject(): string;

    /**
     * Template path under the templates directory of the Email service, without extension
     */
    abstract protected function template(): string;

    public function templateName(): string
    {
        return $this->template();
    }

    /**
     * Copies (Cc header)
     *
     * @return array<string|array{name?: string, email: string}>
     */
    public function cc(): array
    {
        return [];
    }

    /**
     * Hidden copies (Bcc header)
     *
     * @return array<string|array{name?: string, email: string}>
     */
    public function bcc(): array
    {
        return [];
    }

    /**
     * Reply address of this email, null for the one of the Mailer
     *
     * @return string|array{name?: string, email: string}|null
     */
    public function replyTo(): string|array|null
    {
        return null;
    }

    /**
     * Title of the layout (Email::setTitle()), empty for none
     */
    public function title(): string
    {
        return '';
    }

    /**
     * Template variables
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return [];
    }

    /**
     * @return string[] absolute file paths
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Extra headers ("Name: value"), added after the ones of the Mailer
     *
     * @return string[]
     */
    public function headers(): array
    {
        return [];
    }

    /**
     * Short description for logs and histories, the subject by default
     */
    public function label(): string
    {
        return $this->subject();
    }

    /**
     * Last touch on the composed email, for the slots of a project layout (preheader, eyebrow…)
     */
    public function configure(Email $email): void
    {
    }

    /**
     * Called by the Mailer once the sending is known: sent, or failed (after the last attempt when queued).
     * Write the history of a model, notify… Not called for the test sendings of the viewer.
     *
     * @param int|null $actor user who asked for the sending, null for none (cron, visitor)
     */
    public function delivered(bool $sent, ?int $actor = null): void
    {
    }

    /**
     * Text typed in the back-office: paragraphs from the line breaks, **bold**, simple HTML kept, the rest sanitized
     */
    public static function formatText(string $text): string
    {
        $text = (string)preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/s', '<strong>$1</strong>', $text);
        return \wpautop(\wp_kses_post($text));
    }
}
