<?php

namespace Coretik\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Coretik\Services\Modals\Container;
use Coretik\Tests\TestCase;

class ModalsContainerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new \ReflectionProperty(Container::class, 'scriptsLoaded'))->setValue(null, false);
    }

    public function testScriptsAreLoadedInAdminOnlyByDefault(): void
    {
        Actions\expectAdded('admin_footer')->twice();
        Actions\expectAdded('wp_footer')->once(); // modals container only

        new Container();
    }

    public function testFrontScriptsCanBeEnabled(): void
    {
        Filters\expectApplied('coretik/modals/load_front_scripts')->once()->andReturn(true);
        Actions\expectAdded('wp_footer')->twice();

        new Container();
    }

    public function testScriptsArePrintedOnceWithAdminStyles(): void
    {
        Functions\when('is_admin')->justReturn(true);
        $container = new Container();

        \ob_start();
        $container->scripts();
        $container->scripts();
        $output = \ob_get_clean();

        $this->assertSame(1, \substr_count($output, '<script>'));
        $this->assertStringContainsString('[data-modal]', $output);
        $this->assertStringContainsString('#modals-container.has-modal', $output);
    }

    public function testNoAdminStylesOnFront(): void
    {
        Functions\when('is_admin')->justReturn(false);

        \ob_start();
        (new Container())->scripts();
        $output = \ob_get_clean();

        $this->assertStringNotContainsString('<style>', $output);
    }
}
