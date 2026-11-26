<?php

namespace Coretik\Tests\Unit\Queue;

use Brain\Monkey\Functions;
use Coretik\Services\Queue\Admin;
use Coretik\Services\Queue\Queue;
use Coretik\Tests\TestCase;

class AdminTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Functions\when('wp_unslash')->returnArg();
        Functions\when('sanitize_text_field')->alias(fn ($value) => trim(strip_tags((string)$value)));
    }

    public function testFiltersKeepKnownStatusesOnly(): void
    {
        $this->assertSame(
            ['status' => 'failed', 'queue' => 'mail', 'group' => 'camp-email-1-abc'],
            Admin::filters(['status' => 'failed', 'queue' => 'mail', 'group' => ' camp-email-1-abc '])
        );
        $this->assertSame(['status' => '', 'queue' => '', 'group' => ''], Admin::filters(['status' => 'unknown']));
    }

    public function testJobNameWithoutNamespace(): void
    {
        $this->assertSame('SendMail', Admin::jobName('Cdl\\Services\\Mail\\SendMail'));
        $this->assertSame('Plain', Admin::jobName('Plain'));
    }

    public function testWorkerIsLateWhenJobsWaitWithoutRecentRun(): void
    {
        Functions\when('wp_next_scheduled')->justReturn(time() + 30);
        $lastRun = time() - 600;
        Functions\when('get_option')->alias(function () use (&$lastRun) {
            return $lastRun;
        });
        $admin = new Admin(new Queue(new MemoryStore()));

        $this->assertTrue($admin->health(3)['late']);
        $this->assertFalse($admin->health(0)['late'], 'Nothing waiting: not late');

        $lastRun = time() - 30;
        $this->assertFalse($admin->health(3)['late']);
    }

    public function testWorkerThatNeverRanIsLate(): void
    {
        Functions\when('wp_next_scheduled')->justReturn(false);
        Functions\when('get_option')->justReturn(0);

        $health = (new Admin(new Queue(new MemoryStore())))->health(1);

        $this->assertNull($health['last']);
        $this->assertTrue($health['late']);
    }
}
