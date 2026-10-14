<?php

namespace Coretik\Tests\Integration;

use Coretik\Tests\Integration\Fixtures\CampModel;
use Coretik\Tests\Integration\Fixtures\PlayerModel;
use Coretik\Tests\Integration\Fixtures\RegistrationModel;

/**
 * camp n-n player through registration: registration post_parent = player, registration shadow taxonomy = camp
 */
class BelongsToManyTest extends IntegrationTestCase
{
    private function camp(): CampModel
    {
        return coretik()->schema('camp')->model(self::factory()->post->create(['post_type' => 'camp']));
    }

    private function player(): PlayerModel
    {
        return coretik()->schema('player')->model(self::factory()->post->create(['post_type' => 'player']));
    }

    private function register(CampModel $camp, PlayerModel $player, array $props = []): RegistrationModel
    {
        $registration = coretik()->schema('registration')->model();
        $registration->post_title = 'Registration';
        $registration->post_status = 'publish';
        foreach ($props as $key => $value) {
            $registration->$key = $value;
        }
        $registration->camp_id = $camp->id();
        $registration->player_id = $player->id();
        return $registration->save();
    }

    public function testParentToParentThroughPostParent(): void
    {
        $camp = $this->camp();
        $players = [$this->player(), $this->player()];
        foreach ($players as $player) {
            $this->register($camp, $player);
        }
        $this->register($this->camp(), $this->player());

        $this->assertEqualsCanonicalizing(
            \array_map(fn ($player) => $player->id(), $players),
            $camp->players()->map->id()->all()
        );
    }

    public function testParentToParentThroughShadowTaxonomy(): void
    {
        $player = $this->player();
        $camps = [$this->camp(), $this->camp()];
        foreach ($camps as $camp) {
            $this->register($camp, $player);
        }
        $this->register($this->camp(), $this->player());

        $this->assertEqualsCanonicalizing(
            \array_map(fn ($camp) => $camp->id(), $camps),
            $player->camps()->map->id()->all()
        );
    }

    public function testRelatedAreOrderedAsPivotsAndUnique(): void
    {
        $camp = $this->camp();
        $first = $this->player();
        $second = $this->player();
        $this->register($camp, $second, ['post_date' => '2026-01-01 10:00:00']);
        $this->register($camp, $first, ['post_date' => '2026-01-02 10:00:00']);
        // Same child registered twice on the same camp
        $this->register($camp, $first, ['post_date' => '2026-01-03 10:00:00']);

        // Pivots by post_date DESC: first (03), first (02), second (01)
        $this->assertSame([$first->id(), $second->id()], $camp->players()->map->id()->all());

        // Pivots by post_date ASC: second (01), first (02), first (03)
        $ascending = $camp->players(fn ($query) => $query->set('order', 'ASC'))->map->id()->all();
        $this->assertSame([$second->id(), $first->id()], $ascending);
    }

    public function testConstraintFiltersPivots(): void
    {
        $camp = $this->camp();
        $kept = $this->player();
        $this->register($camp, $kept);
        $this->register($camp, $this->player(), ['post_status' => 'draft']);

        $published = $camp->players(fn ($query) => $query->set('post_status', 'publish'));

        $this->assertSame([$kept->id()], $published->map->id()->all());
    }

    public function testWithoutPivotIsEmpty(): void
    {
        $this->register($this->camp(), $this->player());

        $this->assertCount(0, $this->camp()->players());
        $this->assertCount(0, $this->player()->camps());
    }

    public function testWithoutPivotRunsNoPostQuery(): void
    {
        $this->register($this->camp(), $this->player());
        $camp = $this->camp();

        $queries = [];
        $recorder = function ($sql) use (&$queries) {
            $queries[] = $sql;
            return $sql;
        };
        add_filter('query', $recorder);
        $this->assertCount(0, $camp->players());
        $this->assertCount(0, $camp->registrations());
        remove_filter('query', $recorder);

        // The camp has no term in the shadow taxonomy: its term is looked up, no post is queried
        $this->assertSame([], \array_values(\array_filter($queries, fn ($sql) => \preg_match('/FROM \S+posts\s/', $sql))));
        $this->assertNotEmpty($queries);
    }

    public function testQueriesAreIndexedAndDoNotLoadPivots(): void
    {
        $camp = $this->camp();
        $this->register($camp, $this->player());
        $this->register($camp, $this->player());

        $queries = [];
        $recorder = function ($sql) use (&$queries) {
            $queries[] = $sql;
            return $sql;
        };
        add_filter('query', $recorder);
        $camp->players(null, false)->ids();
        remove_filter('query', $recorder);

        $posts = \array_values(\array_filter($queries, fn ($sql) => \preg_match('/FROM \S+posts\s/', $sql)));
        $this->assertCount(2, $posts);
        // Pivots: ID and post_parent columns only, filtered on the shadow taxonomy
        $this->assertMatchesRegularExpression('/SELECT\s+\S+posts\.ID, \S+posts\.post_parent\s+FROM/', $posts[0]);
        $this->assertStringContainsString('term_relationships', $posts[0]);
        // Related posts by primary key
        $this->assertStringContainsString("posts.ID IN", $posts[1]);
        $this->assertStringContainsString("post_type = 'player'", $posts[1]);
        foreach ($queries as $sql) {
            $this->assertDoesNotMatchRegularExpression('/postmeta\.meta_(key|value)/', $sql);
        }
    }
}
