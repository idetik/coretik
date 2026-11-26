<?php

namespace Coretik\Tests\Unit\SchemaViewer;

use Brain\Monkey\Functions;
use Coretik\Core\Models\Handlers\Relationship\ShadowTaxonomyHandler;
use Coretik\Tests\TestCase;
use Coretik\Tests\Unit\SchemaViewer\Fixtures\FakeBuilder;

class ShadowTaxonomiesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Functions\when('sanitize_key')->alias(fn ($key) => \strtolower($key));
        Functions\when('did_action')->justReturn(false);
        (new \ReflectionProperty(ShadowTaxonomyHandler::class, 'shadows'))->setValue(null, []);
        (new \ReflectionProperty(ShadowTaxonomyHandler::class, 'taxonomies'))->setValue(null, []);
    }

    /**
     * ShadowTaxonomyHandler::withPivot('camp_id')->setBelongsToBuilder('camp') on the registration post type
     */
    public static function attach(string $pivot = 'camp_id'): ShadowTaxonomyHandler
    {
        $camp = new FakeBuilder('camp', 'post');
        $handler = (new \ReflectionClass(ShadowTaxonomyHandler::class))->newInstanceWithoutConstructor();
        $handler->setPivot($pivot);
        (new \ReflectionProperty(ShadowTaxonomyHandler::class, 'belongsToBuilder'))->setValue($handler, $camp);
        $handler->handle(new FakeBuilder('registration', 'post'));
        return $handler;
    }

    public function testHandlerRecordsItsShadowTaxonomy(): void
    {
        static::attach();

        $this->assertSame(
            ['camp_registration' => ['parent' => 'camp', 'child' => 'registration', 'pivot' => 'camp_id']],
            ShadowTaxonomyHandler::shadowTaxonomies()
        );
    }

    public function testShadowTaxonomyIsMarkedInTheViewer(): void
    {
        Functions\when('wp_count_terms')->justReturn('12');
        Functions\when('esc_html')->returnArg();
        static::attach();

        \ob_start();
        foreach (ShadowTaxonomyHandler::shadowTaxonomies() as $taxonomy => $shadow) {
            include \dirname(__DIR__, 3) . '/src/Services/SchemaViewer/views/shadow-taxonomy-single.php';
        }
        $html = \ob_get_clean();

        $this->assertStringContainsString('schema-data--shadow', $html);
        $this->assertStringContainsString('<span class="schema-badge"', $html);
        $this->assertStringContainsString('camp_registration', $html);
        $this->assertStringContainsString('<code>camp_id</code>', $html);
        $this->assertStringContainsString('<b>Terms</b>: 12', $html);
    }
}
