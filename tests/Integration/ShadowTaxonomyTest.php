<?php

namespace Coretik\Tests\Integration;

use Coretik\Core\Models\Handlers\Relationship\ShadowTaxonomyHandler;
use Coretik\Tests\Integration\Fixtures\CampModel;
use Coretik\Tests\Integration\Fixtures\RegistrationModel;

/**
 * camp 1-n registration (shadow taxonomy "camp_registration"), registration n-1 player (post_parent)
 */
class ShadowTaxonomyTest extends IntegrationTestCase
{
    private function camp(): CampModel
    {
        $camp = coretik()->schema('camp')->model();
        $camp->post_title = 'Camp';
        $camp->post_status = 'publish';
        return $camp->save();
    }

    private function registration(int $campId, int $playerId = 0): RegistrationModel
    {
        $registration = coretik()->schema('registration')->model();
        $registration->post_title = 'Registration';
        $registration->post_status = 'publish';
        $registration->camp_id = $campId;
        if ($playerId) {
            $registration->player_id = $playerId;
        }
        return $registration->save();
    }

    private function player(): int
    {
        return self::factory()->post->create(['post_type' => 'player']);
    }

    public function testTaxonomyIsRegisteredHidden(): void
    {
        $this->assertSame('camp_registration', ShadowTaxonomyHandler::taxonomyBetween('camp', 'registration'));
        $this->assertNull(ShadowTaxonomyHandler::taxonomyBetween('registration', 'camp'));

        $taxonomy = get_taxonomy('camp_registration');
        $this->assertFalse($taxonomy->public);
        $this->assertFalse($taxonomy->show_ui);
        $this->assertFalse($taxonomy->show_in_rest);
        $this->assertFalse($taxonomy->rewrite);
        $this->assertSame(['registration'], $taxonomy->object_type);
    }

    public function testPivotMountsOneTermPerParent(): void
    {
        $camp = $this->camp();
        $first = $this->registration($camp->id());
        $second = $this->registration($camp->id());

        $terms = get_terms(['taxonomy' => 'camp_registration', 'hide_empty' => false]);
        $this->assertCount(1, $terms);
        $this->assertSame((string)$camp->id(), $terms[0]->slug);
        $this->assertSame([$terms[0]->term_id], wp_get_object_terms($first->id(), 'camp_registration', ['fields' => 'ids']));
        $this->assertSame([$terms[0]->term_id], wp_get_object_terms($second->id(), 'camp_registration', ['fields' => 'ids']));
    }

    public function testPostParentIsKeptForTheOtherRelationship(): void
    {
        $camp = $this->camp();
        $player = $this->player();
        $registration = $this->registration($camp->id(), $player);

        $this->assertSame($player, (int)get_post_field('post_parent', $registration->id()));
        $this->assertSame($player, $registration->player()->id());
        $this->assertSame($camp->id(), $registration->camp()->id());
    }

    public function testHasManyAndHasOne(): void
    {
        $camp = $this->camp();
        $expected = [
            $this->registration($camp->id(), $this->player())->id(),
            $this->registration($camp->id(), $this->player())->id(),
        ];
        $this->registration($this->camp()->id());

        $this->assertEqualsCanonicalizing($expected, $camp->registrations()->map->id()->all());
        $this->assertSame(2, $camp->registrations(false)->count());
        $this->assertContains($camp->firstRegistration()->id(), $expected);
    }

    public function testParentWithoutChildren(): void
    {
        $this->registration($this->camp()->id());
        $camp = $this->camp();

        $this->assertCount(0, $camp->registrations());
        $this->assertNull($camp->firstRegistration());
    }

    public function testChangingThePivotMovesTheChild(): void
    {
        $from = $this->camp();
        $to = $this->camp();
        $registration = $this->registration($from->id());

        update_post_meta($registration->id(), 'camp_id', $to->id());

        $this->assertCount(0, $from->registrations());
        $this->assertSame([$registration->id()], $to->registrations()->map->id()->all());
    }

    public function testDeletingThePivotDetachesTheChild(): void
    {
        $camp = $this->camp();
        $registration = $this->registration($camp->id());

        delete_post_meta($registration->id(), 'camp_id');

        $this->assertCount(0, $camp->registrations());
        $this->assertNull(coretik()->schema('registration')->model($registration->id(), null, true)->camp());
    }

    public function testPivotOnAnotherPostTypeIsIgnored(): void
    {
        $registration = $this->registration($this->player());

        $this->assertSame([], wp_get_object_terms($registration->id(), 'camp_registration', ['fields' => 'ids']));
        $this->assertNull($registration->camp());
    }

    public function testDeletingTheParentDeletesItsTerm(): void
    {
        $camp = $this->camp();
        $registration = $this->registration($camp->id());

        wp_delete_post($camp->id(), true);

        $this->assertFalse(get_term_by('slug', (string)$camp->id(), 'camp_registration'));
        $this->assertSame([], wp_get_object_terms($registration->id(), 'camp_registration', ['fields' => 'ids']));
    }

    public function testHasManyDoesNotSearchMetaValues(): void
    {
        $camp = $this->camp();
        $this->registration($camp->id());

        $queries = [];
        $recorder = function ($sql) use (&$queries) {
            $queries[] = $sql;
            return $sql;
        };
        add_filter('query', $recorder);
        $camp->registrations(false)->ids();
        remove_filter('query', $recorder);

        $relationshipQueries = \array_filter($queries, fn ($sql) => \str_contains($sql, "post_type = 'registration'"));
        $this->assertCount(1, $relationshipQueries);
        $this->assertStringContainsString('term_relationships', \current($relationshipQueries));
        foreach ($queries as $sql) {
            $this->assertDoesNotMatchRegularExpression('/postmeta\.meta_(key|value)/', $sql);
        }
    }
}
