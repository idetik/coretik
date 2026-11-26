<?php

namespace Coretik\Tests\Unit\Migrations;

use Coretik\App;
use Coretik\Core\Container;
use Coretik\Services\Migrations\Migrations;
use Coretik\Tests\TestCase;
use Mockery;

/**
 * App::init() starts the migrations service, not a "migrations" service of the project
 */
class AppInitTest extends TestCase
{
    private function init(array $services): void
    {
        $app = (new \ReflectionClass(App::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(App::class, 'container'))->setValue($app, new Container($services));
        (new \ReflectionMethod(App::class, 'init'))->invoke($app);
    }

    public function testListensToTheMigrationsService(): void
    {
        $migrations = Mockery::mock(Migrations::class);
        $migrations->expects('listen')->once();

        $this->init(['migrations' => fn () => $migrations]);
    }

    public function testIgnoresAnotherMigrationsService(): void
    {
        $project = new class {
            public bool $listened = false;

            public function listen(): void
            {
                $this->listened = true;
            }
        };

        $this->init(['migrations' => fn () => $project]);

        $this->assertFalse($project->listened);
    }
}
