<?php

namespace Coretik\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Functions\when('is_wp_error')->alias(fn ($thing) => $thing instanceof \WP_Error);
        Functions\when('maybe_serialize')->alias(fn ($data) => \is_array($data) || \is_object($data) ? \serialize($data) : $data);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }
}
