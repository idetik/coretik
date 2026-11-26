<?php

namespace Coretik\Tests\Unit;

use Brain\Monkey\Functions;
use Coretik\App;
use Coretik\Core\Container;
use Coretik\Services\Assets\Loader;
use Coretik\Services\Notices\Container as Notices;
use Coretik\Tests\TestCase;
use Mockery;

class SaferDefaultsTest extends TestCase
{
    private function loader(): Loader
    {
        Functions\when('get_stylesheet_directory')->justReturn('/theme');
        Functions\when('get_stylesheet_directory_uri')->justReturn('https://example.com/theme');
        Functions\when('get_template_directory_uri')->justReturn('https://example.com/theme');
        return new Loader('dist/', 0, 'app');
    }

    public function testScriptsAreNotAsyncByDefault(): void
    {
        Functions\expect('wp_enqueue_script')->once()->with('app/main', 'https://example.com/theme/dist/main.js', ['jquery'], null, ['in_footer' => true]);
        Functions\expect('add_filter')->never();

        $this->loader()->enqueueScript('main', 'main.js', ['jquery']);
    }

    public function testAsyncUsesWordPressStrategy(): void
    {
        Functions\expect('wp_enqueue_script')->once()->with('app/main', \Mockery::any(), [], null, ['in_footer' => true, 'strategy' => 'async']);

        $this->loader()->enqueueScript('main', 'main.js', [], null, true, true);
    }

    public function testSchemaViewerIsDisabledWithoutDebug(): void
    {
        $this->assertFalse(\defined('WP_DEBUG') && \WP_DEBUG, 'WP_DEBUG must not be enabled in unit tests');
        \Brain\Monkey\Actions\expectAdded('admin_menu')->never();

        $app = (new \ReflectionClass(App::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(App::class, 'container'))->setValue($app, new Container());
        (new \ReflectionMethod(App::class, 'init'))->invoke($app);

        $this->addToAssertionCount(1);
    }

    public function testNoticesWaitForPluggableFunctions(): void
    {
        $notices = new class extends Notices {
            public bool $pluggable = false;
            public bool $initializedCalled = false;

            protected function pluggableLoaded(): bool
            {
                return $this->pluggable;
            }

            protected function initialize()
            {
                $this->initializedCalled = true;
                $this->initialized = true;
            }

            public function notify(): void
            {
            }
        };
        Functions\expect('add_action')->once()->with('init', [$notices, 'listen'], 0);

        $notices->listen();
        $this->assertFalse($notices->initializedCalled);

        // On init, pluggable functions are loaded
        $notices->pluggable = true;
        $notices->listen();
        $this->assertTrue($notices->initializedCalled);
    }
}
