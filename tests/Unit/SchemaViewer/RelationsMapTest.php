<?php

namespace Coretik\Tests\Unit\SchemaViewer;

use Coretik\Core\BuilderCollection;
use Coretik\Core\Models\Handlers\Relationship\BelongsToHandler;
use Coretik\Core\Models\Handlers\Relationship\ShadowTaxonomyHandler;
use Coretik\Core\Schema;
use Coretik\Services\SchemaViewer\Relations\Relation;
use Coretik\Services\SchemaViewer\Relations\RelationsMap;
use Coretik\Tests\TestCase;
use Coretik\Tests\Unit\SchemaViewer\Fixtures;

class RelationsMapTest extends TestCase
{
    private function schema(array $builders): Schema
    {
        $schema = (new \ReflectionClass(Schema::class))->newInstanceWithoutConstructor();
        $objects = [];
        foreach ($builders as $builder) {
            $objects[$builder->getType()] ??= new BuilderCollection();
            $objects[$builder->getType()]->set($builder->getName(), $builder);
        }
        (new \ReflectionProperty(Schema::class, 'objects'))->setValue($schema, $objects);
        return $schema;
    }

    private function belongsTo(string $pivot, $target = null): BelongsToHandler
    {
        $handler = BelongsToHandler::withPivot($pivot);
        (new \ReflectionProperty(BelongsToHandler::class, 'belongsToBuilder'))->setValue($handler, $target);
        return $handler;
    }

    /**
     * The cdl-gescamps schema
     */
    private function map(): RelationsMap
    {
        $camp = new Fixtures\FakeBuilder('camp', 'post', [], Fixtures\Camp::class);
        $tutor = new Fixtures\FakeBuilder('tutor', 'user', [], Fixtures\Tutor::class);
        $player = (new Fixtures\FakeBuilder('player', 'post', [], Fixtures\Player::class))
            ->handler($this->belongsTo('tutor_id', $tutor));
        $registration = (new Fixtures\FakeBuilder('registration', 'post', [], Fixtures\Registration::class))
            ->handler($this->belongsTo('player_id', $player));
        $page = new Fixtures\FakeBuilder('page', 'post', ['hierarchical' => true]);
        $category = new Fixtures\FakeBuilder('camp_category', 'taxonomy', [], null, ['camp']);

        return new RelationsMap($this->schema([$camp, $tutor, $player, $registration, $page, $category]));
    }

    private function labels(RelationsMap $map): array
    {
        $labels = \array_map(fn (Relation $relation) => "{$relation->from} {$relation->label()} {$relation->to}", $map->relations());
        \sort($labels);
        return $labels;
    }

    public function testRelationsOfTheSchema(): void
    {
        $this->assertSame([
            'post:camp hasMany post:registration',
            'post:camp taxonomy taxonomy:camp_category',
            'post:page parent post:page',
            'post:player hasMany (player_id) post:registration',
            'user:tutor hasMany (tutor_id) post:player',
        ], $this->labels($this->map()));
    }

    public function testMermaidDiagram(): void
    {
        $mermaid = $this->map()->mermaid();

        $this->assertStringStartsWith('erDiagram', $mermaid);
        $this->assertStringContainsString('camp ||--o{ registration : "hasMany"', $mermaid);
        $this->assertStringContainsString('tutor ||--o{ player : "hasMany (tutor_id)"', $mermaid);
        $this->assertStringContainsString('camp }o--o{ camp_category : "taxonomy"', $mermaid);
        $this->assertStringContainsString('page |o--o{ page : "parent"', $mermaid);
    }

    public function testHandlerWithoutTargetGivesThePivotOfTheOnlyBelongsTo(): void
    {
        $tutor = new Fixtures\FakeBuilder('tutor', 'user', [], Fixtures\Tutor::class);
        $player = (new Fixtures\FakeBuilder('player', 'post', [], Fixtures\Player::class))
            ->handler($this->belongsTo('tutor_id'));

        $this->assertContains('user:tutor hasMany (tutor_id) post:player', $this->labels(new RelationsMap($this->schema([$tutor, $player]))));
    }

    public function testSameNameInTwoTypes(): void
    {
        $post = new Fixtures\FakeBuilder('event', 'post', [], null);
        $taxonomy = new Fixtures\FakeBuilder('event', 'taxonomy', [], null, ['event']);

        $this->assertStringContainsString('post_event }o--o{ taxonomy_event', (new RelationsMap($this->schema([$post, $taxonomy])))->mermaid());
    }

    public function testShadowTaxonomyHandlerPivot(): void
    {
        \Brain\Monkey\Functions\when('sanitize_key')->alias(fn ($key) => \strtolower($key));
        \Brain\Monkey\Functions\when('did_action')->justReturn(false);
        $camp = new Fixtures\FakeBuilder('camp', 'post', [], Fixtures\Camp::class);
        $handler = (new \ReflectionClass(ShadowTaxonomyHandler::class))->newInstanceWithoutConstructor();
        $handler->setPivot('camp_id');
        (new \ReflectionProperty(ShadowTaxonomyHandler::class, 'belongsToBuilder'))->setValue($handler, $camp);
        $registration = (new Fixtures\FakeBuilder('registration', 'post', [], Fixtures\Registration::class))->handler($handler);
        $handler->handle($registration);

        $this->assertContains('post:camp hasMany (camp_id via camp_registration) post:registration', $this->labels(new RelationsMap($this->schema([$camp, $registration]))));
    }
}
