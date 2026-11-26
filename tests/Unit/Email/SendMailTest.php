<?php

namespace Coretik\Tests\Unit\Email;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Coretik\Services\Email\Mailer;
use Coretik\Services\Email\SendMail;
use Coretik\Tests\TestCase;

class SendMailTest extends TestCase
{
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
        Functions\when('get_current_user_id')->justReturn(0);

        $mailer = new Mailer(fn () => new FakeEmail());
        Filters\expectApplied('coretik/mail/mailer')->andReturn($mailer);
    }

    public function testPayloadRoundTrip(): void
    {
        $job = new SendMail(new TestMail('p@example.org', 'Hello'), 5);

        $rebuilt = SendMail::fromPayload(json_decode((string)json_encode($job->toPayload()), true));

        $this->assertSame('Hello', $rebuilt->mail()->subject());
        $this->assertSame(5, $rebuilt->actor());
        $this->assertSame('mail', $rebuilt->queue());
        $this->assertSame(3, $rebuilt->tries());
        $this->assertSame(120, $rebuilt->backoff(2));
    }

    public function testUnknownMailClass(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        SendMail::fromPayload(['mail' => \stdClass::class, 'data' => []]);
    }

    public function testMailWithoutObject(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        SendMail::fromPayload(['mail' => TestMail::class, 'data' => ['subject' => '']]);
    }

    public function testHandleSendsWithTheActor(): void
    {
        (new SendMail(new TestMail(), 5))->handle();

        $this->assertCount(1, FakeEmail::$sent);
        $this->assertSame([['key' => 'test.hello', 'sent' => true, 'actor' => 5]], TestMail::$delivered);
    }

    public function testFailedAttemptThrowsWithoutRecording(): void
    {
        FakeEmail::$result = false;

        try {
            (new SendMail(new TestMail(), 5))->handle();
            $this->fail('An exception retries the attempt');
        } catch (\RuntimeException) {
        }

        $this->assertSame([], TestMail::$delivered);
    }

    public function testLastFailureIsRecorded(): void
    {
        (new SendMail(new TestMail(), 5))->failed(new \RuntimeException('SMTP down'));
        $this->assertSame([['key' => 'test.hello', 'sent' => false, 'actor' => 5]], TestMail::$delivered);
    }

    public function testNoRecipientAnymore(): void
    {
        (new SendMail(new TestMail(''), 5))->handle();
        $this->assertSame([], FakeEmail::$sent);
    }
}
