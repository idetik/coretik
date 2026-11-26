<?php

namespace Coretik\Tests\Unit\Forms;

use Brain\Monkey\Functions;
use Coretik\Services\Forms\Config;
use Coretik\Services\Forms\Core\Locator;
use Coretik\Tests\TestCase;

/**
 * A template missing in the theme falls back to the default views of Coretik
 */
class LocatorTest extends TestCase
{
    public function testThemeTemplateComesFirst(): void
    {
        Functions\when('locate_template')->alias(fn ($path) => '/theme/' . $path);

        $locator = new Locator(new Config());

        $this->assertSame('/theme/templates/forms/tunnel-form.php', $locator->locateTemplate('tunnel-form'));
        $this->assertSame('/theme/templates/forms/parts/json-form-open.php', $locator->locatePart('parts/json-form-open'));
    }

    public function testMissingTemplateFallsBackToTheDefaultView(): void
    {
        Functions\when('locate_template')->justReturn('');

        $locator = new Locator(new Config());

        $this->assertSame(Locator::defaultViewsDir() . '/tunnel-form.php', $locator->locateTemplate('tunnel-form'));
        $this->assertSame(Locator::defaultViewsDir() . '/steps/summary.php', $locator->locatePart('steps/summary'));
        $this->assertFileExists($locator->locatePart('parts/json-form-open'));
        $this->assertFileExists($locator->locatePart('fields/spam'));
    }

    public function testNoDefaultViewIsAnEmptyString(): void
    {
        Functions\when('locate_template')->justReturn('');

        $locator = new Locator(new Config());

        $this->assertSame('', $locator->locateTemplate('contact'));
        $this->assertSame('', $locator->locatePart('../Form'));
    }
}
