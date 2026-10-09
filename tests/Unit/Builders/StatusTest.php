<?php

namespace Coretik\Tests\Unit\Builders;

use Brain\Monkey\Functions;
use Coretik\Core\Builders\Status;
use Coretik\Tests\TestCase;

class StatusTest extends TestCase
{
    public function testArgsAreMergedWithDefaults(): void
    {
        $status = new Status('archived', ['label' => 'Archived', 'public' => true]);

        $this->assertSame('Archived', $status->args()->get('label'));
        $this->assertTrue($status->args()->get('public'));
        $this->assertFalse($status->args()->get('label_count'));
        $this->assertTrue($status->args()->has('show_in_admin_all_list'));
    }

    public function testRegister(): void
    {
        Functions\expect('register_post_status')
            ->once()
            ->with('archived', \Mockery::on(fn ($args) => 'Archived' === $args['label'] && false === $args['label_count']));

        (new Status('archived', ['label' => 'Archived']))->registerAction();
    }
}
