<?php

namespace Coretik\Tests\Unit\Testing;

use Coretik\Testing\Bootstrap;
use Coretik\Tests\TestCase;

class BootstrapTest extends TestCase
{
    private const DB_ENV = ['WP_TESTS_DB_NAME', 'WP_TESTS_DB_USER', 'WP_TESTS_DB_PASSWORD', 'WP_TESTS_DB_HOST'];

    private array $env = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (self::DB_ENV as $name) {
            $this->env[$name] = \getenv($name);
            \putenv($name);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->env as $name => $value) {
            \putenv(false === $value ? $name : $name . '=' . $value);
        }
        parent::tearDown();
    }

    public function testSafeDefaults(): void
    {
        $constants = Bootstrap::make('/project/')->constants();

        $this->assertSame('wordpress_tests', $constants['DB_NAME']);
        $this->assertSame('root', $constants['DB_USER']);
        $this->assertSame('root', $constants['DB_PASSWORD']);
        $this->assertSame('127.0.0.1:3307', $constants['DB_HOST']);
        $this->assertSame('/project/vendor/johnpbloch/wordpress-core/', $constants['ABSPATH']);
        $this->assertDirectoryDoesNotExist($constants['WPMU_PLUGIN_DIR'], 'No must-use plugin');
        $this->assertTrue($constants['WP_HTTP_BLOCK_EXTERNAL']);
        $this->assertTrue($constants['WP_DEBUG']);
        $this->assertSame(\PHP_BINARY, $constants['WP_PHP_BINARY']);
        $this->assertArrayNotHasKey('WP_DEFAULT_THEME', $constants, 'Test suite default theme');
    }

    public function testDatabaseFromTheEnvironmentFirst(): void
    {
        \putenv('WP_TESTS_DB_NAME=ci_tests');
        \putenv('WP_TESTS_DB_HOST=mysql:3306');

        $constants = Bootstrap::make('/project')->database('local_tests')->constants();

        $this->assertSame('ci_tests', $constants['DB_NAME']);
        $this->assertSame('mysql:3306', $constants['DB_HOST']);

        \putenv('WP_TESTS_DB_NAME');
        $this->assertSame('local_tests', Bootstrap::make('/project')->database('local_tests')->constants()['DB_NAME']);
    }

    public function testPathsAreResolvedFromTheProjectRoot(): void
    {
        $constants = Bootstrap::make('/project')
            ->wordpress('web/wp/')
            ->content('web/app')
            ->muPlugins('/abs/mu')
            ->theme('my-theme')
            ->constants();

        $this->assertSame('/project/web/wp/', $constants['ABSPATH']);
        $this->assertSame('/project/web/app', $constants['WP_CONTENT_DIR']);
        $this->assertSame('/project/web/app/plugins', $constants['WP_PLUGIN_DIR']);
        $this->assertSame('/abs/mu', $constants['WPMU_PLUGIN_DIR']);
        $this->assertSame('my-theme', $constants['WP_DEFAULT_THEME']);

        $this->assertSame('/project/web/app/modules', Bootstrap::make('/project')->content('web/app', 'web/app/modules')->constants()['WP_PLUGIN_DIR']);
    }

    public function testDefinedConstantsOverrideTheDefaults(): void
    {
        $constants = Bootstrap::make('/project')
            ->define('WP_HTTP_BLOCK_EXTERNAL', false)
            ->define('WP_ENV', 'testing')
            ->define('DB_NAME', 'forced_tests')
            ->constants();

        $this->assertFalse($constants['WP_HTTP_BLOCK_EXTERNAL']);
        $this->assertSame('testing', $constants['WP_ENV']);
        $this->assertSame('forced_tests', $constants['DB_NAME']);
    }

    public function testRefusesADatabaseWhichIsNotATestOne(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('"production" would be emptied');

        Bootstrap::make('/project')->database('production')->payload();
    }

    public function testConfigFileDefinesThePayloadConstants(): void
    {
        $payload = Bootstrap::make('/project')
            ->database('my_tests')
            ->tablePrefix('kit_')
            ->define('WP_TESTS_TITLE', 'Kit')
            ->payload();

        // Loaded alone, as by the process which installs WordPress (no autoloader)
        $code = 'require $argv[1]; echo json_encode([DB_NAME, WP_TESTS_TITLE, ABSPATH, WP_DEBUG, $table_prefix]);';
        $output = $this->php($code, ['CORETIK_TESTS_CONFIG' => $payload], $status);

        $this->assertSame(0, $status, $output);
        $this->assertSame(['my_tests', 'Kit', '/project/vendor/johnpbloch/wordpress-core/', true, 'kit_'], \json_decode($output, true));
    }

    public function testConfigFileStopsWithoutPayload(): void
    {
        $output = $this->php('require $argv[1]; echo "loaded";', [], $status);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('Coretik\Testing\Bootstrap::run()', $output);
        $this->assertStringNotContainsString('loaded', $output);
    }

    /**
     * Runs PHP code in a new process, with the kit configuration file as first argument
     */
    private function php(string $code, array $env, ?int &$status): string
    {
        $config = \dirname(__DIR__, 3) . '/src/Testing/wp-tests-config.php';
        $process = \proc_open(
            [\PHP_BINARY, '-r', $code, $config],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            $env + ['PATH' => (string)\getenv('PATH')]
        );
        $output = \stream_get_contents($pipes[1]) . \stream_get_contents($pipes[2]);
        $status = \proc_close($process);
        return $output;
    }
}
