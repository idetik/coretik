<?php

namespace Coretik\Tests\Integration;

use Coretik\Core\Builders\PostType;
use Coretik\Core\Builders\Taxonomy;
use Coretik\Core\Builders\UserType;
use Coretik\Core\Exception\ContainerValueNotFoundException;
use Coretik\Core\Schema;
use Coretik\Tests\Integration\Fixtures\CampModel;

/**
 * Typed builders of the schema: postType(), taxonomy(), userType() (fixtures: tests/Integration/bootstrap.php)
 */
class SchemaAccessorsTest extends IntegrationTestCase
{
    public function testSchemaOrABuilder(): void
    {
        $this->assertInstanceOf(Schema::class, coretik()->schema());
        $this->assertSame(coretik()->schema()->get('camp'), coretik()->schema('camp'));
        $this->assertNull(coretik()->schema('missing'));
    }

    public function testPostType(): void
    {
        $builder = coretik()->schema()->postType('camp');

        $this->assertInstanceOf(PostType::class, $builder);
        $this->assertSame('camp', $builder->getName());
        // By model class too
        $this->assertSame($builder, coretik()->schema()->postType(CampModel::class));
    }

    public function testTaxonomyAndUserType(): void
    {
        $this->assertInstanceOf(Taxonomy::class, coretik()->schema()->taxonomy('genre'));
        $this->assertInstanceOf(UserType::class, coretik()->schema()->userType('coach'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function missing(): array
    {
        return [
            'unknown post type' => ['postType', 'missing'],
            'built-in post type' => ['postType', 'post'],
            'taxonomy as a post type' => ['postType', 'genre'],
            'built-in taxonomy' => ['taxonomy', 'category'],
            'built-in users' => ['userType', 'users'],
        ];
    }

    /**
     * @dataProvider missing
     */
    public function testMissingBuilderThrows(string $method, string $name): void
    {
        $this->expectException(ContainerValueNotFoundException::class);
        $this->expectExceptionMessage(sprintf('"%s"', $name));

        coretik()->schema()->$method($name);
    }
}
