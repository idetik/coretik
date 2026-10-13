<?php

namespace Coretik\Tests\Integration;

use Coretik\Tests\Integration\Fixtures\PlayerModel;
use Coretik\Tests\Integration\Fixtures\RegistrationModel;
use Coretik\Tests\Integration\Fixtures\TutorModel;

/**
 * tutor (user) 1-n player (post_author) 1-n registration (post_parent)
 */
class RelationshipsTest extends IntegrationTestCase
{
    private function tutor(): TutorModel
    {
        return coretik()->schema('users', 'user')->model(self::factory()->user->create());
    }

    private function player(int $tutorId): PlayerModel
    {
        $player = coretik()->schema('player')->model();
        $player->post_title = 'Player';
        $player->post_status = 'publish';
        $player->tutor_id = $tutorId;
        return $player->save();
    }

    private function registration(int $playerId): RegistrationModel
    {
        $registration = coretik()->schema('registration')->model();
        $registration->post_title = 'Registration';
        $registration->post_status = 'publish';
        $registration->player_id = $playerId;
        return $registration->save();
    }

    private function recordQueries(callable $callback): array
    {
        $queries = [];
        $recorder = function ($sql) use (&$queries) {
            $queries[] = $sql;
            return $sql;
        };
        add_filter('query', $recorder);
        $callback();
        remove_filter('query', $recorder);
        return $queries;
    }

    public function testSchemaResolvesBuilderByModelClass(): void
    {
        $this->assertSame('registration', coretik()->schema()->get(RegistrationModel::class)->getName());
        $this->assertSame('registration', coretik()->schema()->get('\\' . RegistrationModel::class, 'post')->getName());
        $this->assertSame('users', coretik()->schema()->get(TutorModel::class)->getName());
        $this->assertNull(coretik()->schema()->get(RegistrationModel::class, 'user'));
    }

    public function testBelongsToHandlerMountsPostAuthorForUsers(): void
    {
        $tutor = $this->tutor();
        $player = $this->player($tutor->id());

        $this->assertSame($tutor->id(), (int)get_post_field('post_author', $player->id()));
        $this->assertSame(0, (int)get_post_field('post_parent', $player->id()));
        $this->assertSame($tutor->id(), $player->tutor()->id());
    }

    public function testBelongsToHandlerMountsPostParentForPosts(): void
    {
        $player = $this->player($this->tutor()->id());
        $registration = $this->registration($player->id());

        $this->assertSame($player->id(), (int)get_post_field('post_parent', $registration->id()));
        $this->assertSame($player->id(), $registration->player()->id());
    }

    public function testUserHasMany(): void
    {
        $tutor = $this->tutor();
        $players = [$this->player($tutor->id())->id(), $this->player($tutor->id())->id()];
        $this->player($this->tutor()->id());

        $this->assertEqualsCanonicalizing($players, $tutor->players()->map->id()->all());
    }

    public function testHasManyThrough(): void
    {
        $tutor = $this->tutor();
        $first = $this->player($tutor->id());
        $second = $this->player($tutor->id());
        $expected = [
            $this->registration($first->id())->id(),
            $this->registration($first->id())->id(),
            $this->registration($second->id())->id(),
        ];

        // Another family
        $this->registration($this->player($this->tutor()->id())->id());

        $this->assertEqualsCanonicalizing($expected, $tutor->registrations()->map->id()->all());
        $this->assertEqualsCanonicalizing($expected, $tutor->registrations(false)->ids());
        $this->assertContains($tutor->lastRegistration()->id(), $expected);
    }

    public function testHasManyThroughWithoutIntermediateIsEmpty(): void
    {
        $this->registration($this->player($this->tutor()->id())->id());
        $tutor = $this->tutor();

        // An empty post_parent__in would return every registration
        $this->assertCount(0, $tutor->registrations());
        $this->assertNull($tutor->lastRegistration());
    }

    public function testHasManyThroughDoesNotSearchMetaValues(): void
    {
        $tutor = $this->tutor();
        $this->registration($this->player($tutor->id())->id());

        $queries = $this->recordQueries(fn () => $tutor->registrations(false)->ids());

        // Intermediate ids, related posts, then the metas cache priming of the related posts (post_id IN)
        $this->assertCount(3, $queries);
        $this->assertMatchesRegularExpression('/SELECT\s+\S+posts\.ID\s+FROM/', $queries[0]);
        $this->assertStringContainsString('post_author IN', $queries[0]);
        $this->assertStringContainsString('post_parent IN', $queries[1]);
        $this->assertMatchesRegularExpression('/FROM \S+postmeta WHERE post_id IN/', $queries[2]);
        foreach (\array_slice($queries, 0, 2) as $sql) {
            $this->assertStringNotContainsString('postmeta', $sql);
        }
    }

    public function testBelongsToThrough(): void
    {
        $tutor = $this->tutor();
        $registration = $this->registration($this->player($tutor->id())->id());

        $this->assertSame($tutor->id(), $registration->tutor()->id());
    }

    public function testBelongsToThroughWithoutIntermediateIsNull(): void
    {
        $registration = coretik()->schema('registration')->model();
        $registration->post_title = 'Orphan';
        $registration->save();

        $this->assertNull($registration->tutor());
    }
}
