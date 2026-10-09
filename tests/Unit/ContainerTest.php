<?php

namespace Coretik\Tests\Unit;

use Coretik\Core\Container;
use Coretik\Core\Schema;
use Coretik\Tests\TestCase;

class ContainerTest extends TestCase
{
    public function testDefaultsAreRegistered(): void
    {
        $container = new Container();

        $this->assertTrue($container->has('schema'));
        $this->assertTrue($container->has('notices'));
        $this->assertSame(['text-domain' => 'coretik'], $container->get('settings'));
    }

    public function testConstructorValuesOverrideDefaults(): void
    {
        $schema = new \stdClass();
        $container = new Container([
            'schema' => fn () => $schema,
            'settings' => ['text-domain' => 'my-theme', 'foo' => 'bar'],
        ]);

        $this->assertSame($schema, $container->get('schema'));
        $this->assertSame(['text-domain' => 'my-theme', 'foo' => 'bar'], $container->get('settings'));
    }

    public function testSettingsKeepDefaultTextDomain(): void
    {
        $container = new Container(['settings' => ['foo' => 'bar']]);

        $this->assertSame(['text-domain' => 'coretik', 'foo' => 'bar'], $container->get('settings'));
    }

    public function testSettingsServiceIsKept(): void
    {
        $settings = new \ArrayObject(['foo' => 'bar']);
        $container = new Container(['settings' => fn () => $settings]);

        $this->assertSame($settings, $container->get('settings'));
    }

    public function testConstructActionIsFired(): void
    {
        \Brain\Monkey\Actions\expectDone('coretik/container/construct')->once();

        new Container();
    }
}
