<?php

namespace Coretik\Tests\Unit;

use Coretik\Services\Email\Email;
use Coretik\Tests\TestCase;

class EmailTest extends TestCase
{
    private function formatTo($to)
    {
        return (new \ReflectionMethod(Email::class, 'formatTo'))->invoke(null, $to);
    }

    public function testSingleRecipientWithName(): void
    {
        $this->assertSame('John <john@example.com>', $this->formatTo(['name' => 'John', 'email' => 'john@example.com']));
    }

    public function testRecipientsList(): void
    {
        $this->assertSame(
            ['John <john@example.com>', 'jane@example.com', 'bob@example.com'],
            $this->formatTo([['name' => 'John', 'email' => 'john@example.com'], 'jane@example.com', ['email' => 'bob@example.com']])
        );
    }

    public function testStringRecipient(): void
    {
        $this->assertSame('john@example.com', $this->formatTo('john@example.com'));
    }
}
