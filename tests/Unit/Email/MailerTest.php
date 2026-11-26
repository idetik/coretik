<?php

namespace Coretik\Tests\Unit\Email;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Coretik\Services\Email\Mailer;
use Coretik\Services\Email\SendMail;
use Coretik\Services\Queue\Queue;
use Coretik\Tests\TestCase;
use Coretik\Tests\Unit\Queue\MemoryStore;

class MailerTest extends TestCase
{
    private Mailer $mailer;

    protected function setUp(): void
    {
        parent::setUp();
        FakeEmail::reset();
        TestMail::reset();

        Functions\when('is_email')->alias(fn ($email) => false !== filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : false);
        Functions\when('home_url')->justReturn('https://www.example.org');
        Functions\when('wp_parse_url')->alias(fn ($url, $component = -1) => parse_url($url, $component));
        Functions\when('get_bloginfo')->alias(fn ($show = '') => 'charset' === $show ? 'UTF-8' : 'My site');
        Functions\when('wp_specialchars_decode')->returnArg();
        Functions\when('get_current_user_id')->justReturn(7);
        Functions\when('wp_strip_all_tags')->alias(fn ($text) => trim(strip_tags((string)$text)));
        Functions\when('update_option')->justReturn(true);

        $this->mailer = (new Mailer(fn () => new FakeEmail()))->useQueue(false);
    }

    public function testRecipientsAreNormalized(): void
    {
        $this->assertSame([['name' => '', 'email' => 'a@example.org']], Mailer::recipients('a@example.org'));
        $this->assertSame([['name' => 'Pierre Dupont', 'email' => 'p@example.org']], Mailer::recipients(['name' => 'Pierre Dupont', 'email' => 'p@example.org']));
        $this->assertSame([['name' => 'Marie', 'email' => 'm@example.org']], Mailer::recipients('"Marie" <m@example.org>'));
        $this->assertSame(
            [['name' => '', 'email' => 'a@example.org'], ['name' => 'B', 'email' => 'b@example.org']],
            Mailer::recipients(['a@example.org', ['name' => 'B', 'email' => 'b@example.org'], ['name' => 'No email'], '', "x@example.org\r\nBcc: evil@example.org"])
        );
        $this->assertSame([], Mailer::recipients(''));
        $this->assertSame([], Mailer::recipients([]));
    }

    /**
     * Names put in From / To / Reply-To headers: no line break nor address delimiter
     */
    public function testHeaderName(): void
    {
        $this->assertSame('Pierre Dupont', Mailer::headerName('Pierre Dupont'));
        $this->assertSame('Pierre Bcc: evil@example.org', Mailer::headerName("Pierre\r\nBcc: evil@example.org"));
        $this->assertSame('Dupont x', Mailer::headerName('Dupont, <x>'));
        $this->assertSame('Comité des Landes', Mailer::headerName('"Comité des Landes"'));
        $this->assertSame('Pierre Bcc: e@x.org <p@example.org>', Mailer::address(['name' => "Pierre\nBcc: e@x.org", 'email' => 'p@example.org']));
        $this->assertSame('p@example.org', Mailer::address(['email' => 'p@example.org']));
    }

    public function testDefaultSenderIsTheNoReplyAddressOfTheSite(): void
    {
        $this->assertSame('noreply@example.org', $this->mailer->fromEmail());
        $this->assertSame('My site', $this->mailer->fromName());
        $this->assertSame(['From: My site <noreply@example.org>'], $this->mailer->headers());

        $this->mailer->from('contact@example.org', 'Contact')->replyTo(['name' => 'Support', 'email' => 'support@example.org']);
        $this->assertSame(['From: Contact <contact@example.org>', 'Reply-To: Support <support@example.org>'], $this->mailer->headers());

        // Invalid address: default one
        $this->assertSame('noreply@example.org', $this->mailer->from('not an email')->fromEmail());
    }

    public function testCompose(): void
    {
        $mail = new TestMail(['name' => 'Pierre', 'email' => 'p@example.org'], 'Hello', ['m@example.org'], [['name' => 'Archive', 'email' => 'archive@example.org']], 'reply@example.org');

        /** @var FakeEmail $email */
        $email = $this->mailer->compose($mail);

        $this->assertSame('Pierre <p@example.org>', $email->to);
        $this->assertSame('Hello', $email->getSubject());
        $this->assertSame('Title', $email->email_title);
        $this->assertSame('test/hello', $email->template);
        $this->assertSame(['name' => 'Pierre'], $email->data);
        $this->assertSame(['/tmp/invoice.pdf'], $email->attachments);
        $this->assertSame([
            'From: My site <noreply@example.org>',
            'Reply-To: reply@example.org',
            'Cc: m@example.org',
            'Bcc: Archive <archive@example.org>',
            'X-Mail: test.hello',
            'X-Configured: 1',
        ], $email->headers);
    }

    public function testSeveralRecipients(): void
    {
        $email = $this->mailer->compose(new TestMail(['a@example.org', ['name' => 'B', 'email' => 'b@example.org']]));
        $this->assertSame(['a@example.org', 'B <b@example.org>'], $email->to);
    }

    public function testHeadersFilter(): void
    {
        Filters\expectApplied('coretik/mail/headers')->once()->andReturnUsing(fn (array $headers) => array_merge($headers, ['X-Site: 1']));
        $this->assertContains('X-Site: 1', $this->mailer->compose(new TestMail())->headers);
    }

    public function testSendRecordsTheDelivery(): void
    {
        Actions\expectDone('coretik/mail/sent')->once()->with(\Mockery::type(TestMail::class), \Mockery::type(FakeEmail::class), 7);

        $this->assertTrue($this->mailer->send(new TestMail()));

        $this->assertCount(1, FakeEmail::$sent);
        $this->assertSame([['key' => 'test.hello', 'sent' => true, 'actor' => 7]], TestMail::$delivered);
    }

    public function testFailureIsRecorded(): void
    {
        FakeEmail::$result = false;
        Actions\expectDone('coretik/mail/failed')->once();

        $this->assertFalse($this->mailer->send(new TestMail()));
        $this->assertSame([['key' => 'test.hello', 'sent' => false, 'actor' => 7]], TestMail::$delivered);
    }

    public function testFailureNotRecordedWhenRetried(): void
    {
        FakeEmail::$result = false;
        $this->assertFalse($this->mailer->deliver(new TestMail(), 3, false));
        $this->assertSame([], TestMail::$delivered);
    }

    public function testNothingSentWithoutRecipient(): void
    {
        $this->assertFalse($this->mailer->send(new TestMail('')));
        $this->assertSame([], FakeEmail::$sent);
        $this->assertSame([], TestMail::$delivered);
    }

    public function testTestSendingGoesToTheTesterOnly(): void
    {
        $this->assertTrue($this->mailer->test(new TestMail('p@example.org', 'Hello', ['m@example.org'], ['b@example.org']), 'admin@example.org'));

        $email = FakeEmail::$sent[0];
        $this->assertSame('admin@example.org', $email->to);
        $this->assertSame('[Test] Hello', $email->getSubject());
        $this->assertSame([], preg_grep('/^(Cc|Bcc):/i', $email->headers));
        $this->assertSame([], TestMail::$delivered, 'Not recorded');
    }

    public function testQueueWithoutQueueSendsNow(): void
    {
        $this->assertNull($this->mailer->queue(new TestMail()));
        $this->assertCount(1, FakeEmail::$sent);
        $this->assertSame(true, TestMail::$delivered[0]['sent']);
    }

    public function testQueuePushesASendMailJob(): void
    {
        $store = new MemoryStore();
        $this->mailer->useQueue(fn () => new Queue($store));

        $id = $this->mailer->queue(new TestMail('p@example.org', 'Newsletter'), 'newsletter-1', 60);

        $this->assertIsInt($id);
        $this->assertSame([], FakeEmail::$sent);
        $job = $store->find($id);
        $this->assertSame(SendMail::class, $job->job);
        $this->assertSame(SendMail::QUEUE, $job->queue);
        $this->assertSame('newsletter-1', $job->group);
        $this->assertSame(['mail' => TestMail::class, 'data' => ['to' => 'p@example.org', 'subject' => 'Newsletter'], 'actor' => 7], $job->payload);
    }

    public function testSyncQueueSendsNowAndRecordsFailures(): void
    {
        FakeEmail::$result = false;
        $this->mailer->useQueue((new Queue(new MemoryStore()))->sync());

        $this->assertNull($this->mailer->queue(new TestMail()));
        $this->assertSame(false, TestMail::$delivered[0]['sent']);
    }

    public function testQueueFilter(): void
    {
        $store = new MemoryStore();
        Filters\expectApplied('coretik/mail/queue')->once()->andReturn(new Queue($store));

        $this->assertNotNull($this->mailer->useQueue(null)->queue(new TestMail()));
        $this->assertCount(1, $store->jobs);
    }

    public function testRecipientLabel(): void
    {
        $this->assertSame(
            'Pierre <p@example.org> (copie : Marie <m@example.org>)',
            Mailer::recipientLabel(new TestMail(['name' => 'Pierre', 'email' => 'p@example.org'], 'Hello', [['name' => 'Marie', 'email' => 'm@example.org']]))
        );
        $this->assertSame('—', Mailer::recipientLabel(new TestMail(''), '—'));
        $this->assertSame('a@example.org (cc: b@example.org)', Mailer::recipientLabel(new TestMail('a@example.org', 'Hello', ['b@example.org']), '', ' (cc: %s)'));
    }

    public function testCoreEmailIsReplaced(): void
    {
        $email = $this->mailer->coreEmail(['to' => 'p@example.org', 'subject' => 'WP', 'message' => 'Text', 'headers' => ''], new TestMail());

        $this->assertSame('p@example.org', $email['to']);
        $this->assertSame('Hello', $email['subject']);
        $this->assertSame('<h1>Title</h1><p>test/hello</p>', $email['message']);
        $this->assertSame(['Content-Type: text/html; charset=UTF-8', 'From: My site <noreply@example.org>'], $email['headers']);
    }

    public function testPlainText(): void
    {
        $html = '<html><head><style>p{color:red}</style></head><body>'
            . '<div class="email-preheader">Hidden</div>'
            . '<h1>Title</h1>'
            . "<p>Hello   Pierre,<br>\n see <a href=\"https://example.org/a?b=1&amp;c=2\">your account</a>.</p>"
            . '<ul><li>One</li><li>Two</li></ul>'
            . '<p><a href="mailto:x@example.org">x@example.org</a> <img src="logo.png" alt="Logo"></p>'
            . '</body></html>';

        $this->assertSame(
            "Title\nHello Pierre,\nsee your account (https://example.org/a?b=1&c=2).\n- One\n- Two\nx@example.org Logo",
            Mailer::plainText($html)
        );
    }

    public function testPhpmailerInit(): void
    {
        $phpmailer = new \stdClass();
        $phpmailer->From = 'noreply@example.org';
        $phpmailer->Sender = '';
        $phpmailer->ContentType = 'text/html';
        $phpmailer->Body = '<p>Hello</p>';
        $phpmailer->AltBody = '';

        $this->mailer->phpmailerInit($phpmailer);

        $this->assertSame('noreply@example.org', $phpmailer->Sender);
        $this->assertSame('Hello', $phpmailer->AltBody);
    }

    public function testHooks(): void
    {
        Functions\when('is_admin')->justReturn(true);
        $this->mailer->viewer(fn ($catalog) => null, ['slug' => 'app-emails']);

        $this->mailer->hooks();
        $this->mailer->hooks();

        $this->assertNotFalse(has_filter('wp_mail_from'));
        $this->assertNotFalse(has_filter('wp_mail_from_name'));
        $this->assertSame(20, has_action('phpmailer_init', [$this->mailer, 'phpmailerInit']));
        $this->assertNotFalse(has_action('admin_post_app_emails_frame'), 'Emails viewer registered');
    }

    public function testHooksWithoutDeliverability(): void
    {
        Functions\when('is_admin')->justReturn(true);

        $this->mailer->deliverability(false)->hooks();

        $this->assertFalse(has_action('phpmailer_init', [$this->mailer, 'phpmailerInit']));
        $this->assertFalse(has_action('admin_menu'), 'No viewer declared');
    }
}
