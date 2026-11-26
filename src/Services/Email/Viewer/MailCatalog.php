<?php

namespace Coretik\Services\Email\Viewer;

use Coretik\Services\Email\Mail;
use Coretik\Services\Email\Mailer;

/**
 * Catalog of the viewer built from the mails of the application: each entry has a sample builder, which makes the
 * mail from the context (sample data). Previews and test sendings go through the Mailer, without side effects.
 *
 * $catalog = (new MailCatalog($mailer))
 *     ->group('Compte')
 *     ->add('account.password_reset', 'Mot de passe oublié', fn (array $context) => new PasswordReset($context['user'], '#'),
 *         'Lien de réinitialisation du mot de passe.', 'Automatique : mot de passe oublié')
 *     ->group('Commandes')
 *     ->add('order.confirmation', 'Confirmation', fn (array $context) => $context['order'] ? new OrderConfirmation($context['order']) : null)
 *     ->withContext(fn (array $query) => ['user' => wp_get_current_user(), 'order' => latest_order()]);
 *
 * Or extend it: register() declares the entries (called once, lazily), context() / contextFields() choose the samples.
 * Default context: ['user' => the current user]. A builder returning null: preview unavailable for this context.
 */
class MailCatalog implements Catalog
{
    /** @var array<string, array{entry: Entry, mail: callable(array): ?Mail}> */
    protected array $definitions = [];

    protected string $group = '';
    protected bool $registered = false;

    /** @var (callable(array<string, string>): array)|null */
    protected $context = null;

    /** @var (callable(array): string)|null */
    protected $fields = null;

    public function __construct(protected Mailer $mailer)
    {
    }

    /**
     * Entries of the subclasses, declared once before the first use
     */
    protected function register(): void
    {
    }

    protected function boot(): void
    {
        if (!$this->registered) {
            $this->registered = true;
            $this->register();
        }
    }

    /**
     * Group of the next entries; with $define, only for the entries it adds
     *
     * @param (callable(static): mixed)|null $define
     */
    public function group(string $group, ?callable $define = null): static
    {
        if (!$define) {
            $this->group = $group;
            return $this;
        }

        $previous = $this->group;
        $this->group = $group;
        try {
            $define($this);
        } finally {
            $this->group = $previous;
        }

        return $this;
    }

    /**
     * An entry and its sample builder, in the current group. Same key: the entry is replaced (kept in place).
     *
     * @param callable(array): ?Mail $mail
     */
    public function add(string $key, string $label, callable $mail, string $description = '', string $trigger = ''): static
    {
        return $this->entry(new Entry($key, $label, $this->group, $description, $trigger), $mail);
    }

    /**
     * @param callable(array): ?Mail $mail
     */
    public function entry(Entry $entry, callable $mail): static
    {
        $this->definitions[$entry->key] = ['entry' => $entry, 'mail' => $mail];
        return $this;
    }

    public function remove(string $key): static
    {
        $this->boot();
        unset($this->definitions[$key]);
        return $this;
    }

    public function has(string $key): bool
    {
        $this->boot();
        return isset($this->definitions[$key]);
    }

    /**
     * Context of the previews from the query string, and its toolbar fields (HTML)
     *
     * @param callable(array<string, string>): array $context
     * @param (callable(array): string)|null $fields
     */
    public function withContext(callable $context, ?callable $fields = null): static
    {
        $this->context = $context;
        $this->fields = $fields;
        return $this;
    }

    public function entries(): array
    {
        $this->boot();
        return array_values(array_column($this->definitions, 'entry'));
    }

    public function context(array $query): array
    {
        if ($this->context) {
            return (array)($this->context)($query);
        }

        return ['user' => \wp_get_current_user()];
    }

    public function contextFields(array $context): string
    {
        return $this->fields ? (string)($this->fields)($context) : '';
    }

    /**
     * The sample mail of an entry for this context, null when unknown or unavailable
     */
    public function mail(string $key, array $context): ?Mail
    {
        $this->boot();
        $definition = $this->definitions[$key] ?? null;
        $mail = $definition ? ($definition['mail'])($context) : null;

        return $mail instanceof Mail ? $mail : null;
    }

    public function preview(string $key, array $context): ?Preview
    {
        $mail = $this->mail($key, $context);
        return $mail ? Preview::fromEmail($this->mailer->compose($mail)) : null;
    }

    /**
     * Mailer::test(): recipient replaced, no copies, nothing recorded
     */
    public function sendTest(string $key, array $context, string $to): bool
    {
        $mail = $this->mail($key, $context);
        return $mail && $this->mailer->test($mail, $to);
    }
}
