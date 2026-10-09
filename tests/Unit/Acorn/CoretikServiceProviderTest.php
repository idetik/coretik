<?php

namespace Coretik\Tests\Unit\Acorn;

use Coretik\Acorn\CoretikServiceProvider;
use Coretik\App;
use Coretik\Core\Container;
use Coretik\Tests\TestCase;
use Illuminate\Container\Container as LaravelContainer;

class CoretikServiceProviderTest extends TestCase
{
    private function setApp(?App $app): void
    {
        (new \ReflectionProperty(App::class, 'instance'))->setValue(null, $app);
    }

    protected function tearDown(): void
    {
        $this->setApp(null);
        parent::tearDown();
    }

    public function testCoretikIsResolvedFromTheAcornContainer(): void
    {
        $laravel = new LaravelContainer();
        (new CoretikServiceProvider($laravel))->register();

        // Acorn may boot before the project runs coretik
        $this->assertNull($laravel->make('coretik'));

        $app = (new \ReflectionClass(App::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(App::class, 'container'))->setValue($app, new Container());
        $this->setApp($app);

        $this->assertSame($app, $laravel->make('coretik'));
        $this->assertSame($app, $laravel->make(App::class));
        $this->assertSame($app, coretik());
    }

    public function testProviderIsDeclaredForPackageDiscovery(): void
    {
        $composer = \json_decode(\file_get_contents(\dirname(__DIR__, 3) . '/composer.json'), true);

        $this->assertContains(CoretikServiceProvider::class, $composer['extra']['laravel']['providers']);
    }
}
