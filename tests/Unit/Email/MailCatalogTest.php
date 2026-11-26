<?php

namespace Coretik\Tests\Unit\Email;

use Brain\Monkey\Functions;
use Coretik\Services\Email\Mailer;
use Coretik\Services\Email\Viewer\MailCatalog;
use Coretik\Services\Email\Viewer\Viewer;
use Coretik\Tests\TestCase;

class MailCatalogTest extends TestCase
{
    private Mailer $mailer;

    protected function setUp(): void
    {
        parent::setUp();
        FakeEmail::reset();
        TestMail::reset();
        Functions\when('is_email')->returnArg();
        Functions\when('home_url')->justReturn('https://example.org');
        Functions\when('wp_parse_url')->alias(fn ($url, $component = -1) => parse_url($url, $component));
        Functions\when('get_bloginfo')->justReturn('My site');
        Functions\when('wp_specialchars_decode')->returnArg();

        $this->mailer = new Mailer(fn () => new FakeEmail());
    }

    private function catalog(): MailCatalog
    {
        return (new MailCatalog($this->mailer))
            ->add('base', 'Composants', fn () => new TestMail('team@example.org', 'Showcase'))
            ->group('Compte', function (MailCatalog $catalog) {
                $catalog->add('account.reset', 'Mot de passe oublié', fn (array $context) => $context['user'] ? new TestMail($context['user'], 'Reset') : null, 'Lien', 'Automatique');
            })
            ->group('Commandes')
            ->add('order.confirmation', 'Confirmation', fn (array $context) => new TestMail('client@example.org', 'Commande ' . $context['order'], ['copy@example.org']))
            ->withContext(
                fn (array $query) => ['user' => $query['user'] ?? null, 'order' => (int)($query['order'] ?? 42)],
                fn (array $context) => sprintf('<input name="order" value="%d">', $context['order'])
            );
    }

    public function testEntriesAndGroups(): void
    {
        $entries = $this->catalog()->entries();

        $this->assertSame(['base', 'account.reset', 'order.confirmation'], array_map(fn ($entry) => $entry->key, $entries));
        $this->assertSame(['', 'Compte', 'Commandes'], array_keys(Viewer::groups($entries)));
        $this->assertSame('Lien', $entries[1]->description);
        $this->assertSame('Automatique', $entries[1]->trigger);
    }

    public function testRemove(): void
    {
        $catalog = $this->catalog()->remove('base');
        $this->assertFalse($catalog->has('base'));
        $this->assertTrue($catalog->has('order.confirmation'));
    }

    public function testContext(): void
    {
        $catalog = $this->catalog();
        $context = $catalog->context(['order' => '7']);

        $this->assertSame(['user' => null, 'order' => 7], $context);
        $this->assertSame('<input name="order" value="7">', $catalog->contextFields($context));
    }

    public function testDefaultContextIsTheCurrentUser(): void
    {
        $user = new \stdClass();
        Functions\when('wp_get_current_user')->justReturn($user);

        $catalog = new MailCatalog($this->mailer);
        $this->assertSame(['user' => $user], $catalog->context([]));
        $this->assertSame('', $catalog->contextFields([]));
    }

    public function testPreview(): void
    {
        $catalog = $this->catalog();

        $preview = $catalog->preview('order.confirmation', $catalog->context([]));
        $this->assertSame('Commande 42', $preview->subject);
        $this->assertSame('client@example.org', $preview->recipients());
        $this->assertSame('copy@example.org', $preview->header('Cc'));
        $this->assertSame('<h1>Title</h1><p>test/hello</p>', $preview->html);

        $this->assertNull($catalog->preview('account.reset', $catalog->context([])), 'No sample user');
        $this->assertNull($catalog->preview('unknown', []));
    }

    public function testSendTestHasNoSideEffects(): void
    {
        $catalog = $this->catalog();

        $this->assertTrue($catalog->sendTest('order.confirmation', $catalog->context([]), 'admin@example.org'));
        $this->assertFalse($catalog->sendTest('account.reset', $catalog->context([]), 'admin@example.org'));

        $this->assertCount(1, FakeEmail::$sent);
        $this->assertSame('admin@example.org', FakeEmail::$sent[0]->to);
        $this->assertSame('[Test] Commande 42', FakeEmail::$sent[0]->getSubject());
        $this->assertSame([], TestMail::$delivered);
    }

    public function testSubclassRegistersItsEntries(): void
    {
        $catalog = new class ($this->mailer) extends MailCatalog {
            public int $calls = 0;

            protected function register(): void
            {
                $this->calls++;
                $this->group('Socle')->add('base', 'Composants', fn () => new TestMail());
            }
        };

        $this->assertSame('Socle', $catalog->entries()[0]->group);
        $catalog->entries();
        $this->assertSame(1, $catalog->calls);
    }

    public function testMailerBuildsItsCatalog(): void
    {
        $this->assertNull($this->mailer->catalog());

        $this->mailer->viewer(fn (MailCatalog $catalog) => $catalog->add('base', 'Composants', fn () => new TestMail()));
        $catalog = $this->mailer->catalog();

        $this->assertInstanceOf(MailCatalog::class, $catalog);
        $this->assertSame($catalog, $this->mailer->catalog());
        $this->assertSame('base', $catalog->entries()[0]->key);
    }
}
