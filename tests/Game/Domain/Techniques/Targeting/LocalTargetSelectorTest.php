<?php

namespace App\Tests\Game\Domain\Techniques\Targeting;

use App\Entity\LocalActor;
use App\Entity\LocalSession;
use App\Game\Domain\LocalMap\AimMode;
use App\Game\Domain\LocalMap\Direction;
use App\Game\Domain\Techniques\Targeting\LocalTargetSelector;
use PHPUnit\Framework\TestCase;

final class LocalTargetSelectorTest extends TestCase
{
    public function testProjectileHitsFirstActorInDirection(): void
    {
        $session  = new LocalSession(worldId: 1, characterId: 1, tileX: 0, tileY: 0, width: 10, height: 10, playerX: 5, playerY: 5);
        $attacker = new LocalActor($session, characterId: 1, role: 'player', x: 5, y: 5);
        $a1       = new LocalActor($session, characterId: 2, role: 'npc', x: 5, y: 3);
        $a2       = new LocalActor($session, characterId: 3, role: 'npc', x: 5, y: 2);

        $targets = (new LocalTargetSelector())->selectTargets(
            attacker: $attacker,
            actors: [$attacker, $a2, $a1],
            aimMode: AimMode::Direction,
            direction: Direction::North,
            targetX: null,
            targetY: null,
            range: 5,
            delivery: 'projectile',
            piercing: null,
            aoeRadius: null,
        );

        self::assertCount(1, $targets);
        self::assertSame(2, $targets[0]->getCharacterId());
    }

    public function testRayWithPiercingAllHitsAllActorsInLine(): void
    {
        $session  = new LocalSession(worldId: 1, characterId: 1, tileX: 0, tileY: 0, width: 10, height: 10, playerX: 5, playerY: 5);
        $attacker = new LocalActor($session, characterId: 1, role: 'player', x: 5, y: 5);
        $a1       = new LocalActor($session, characterId: 2, role: 'npc', x: 5, y: 4);
        $a2       = new LocalActor($session, characterId: 3, role: 'npc', x: 5, y: 3);

        $targets = (new LocalTargetSelector())->selectTargets(
            attacker: $attacker,
            actors: [$attacker, $a1, $a2],
            aimMode: AimMode::Direction,
            direction: Direction::North,
            targetX: null,
            targetY: null,
            range: 5,
            delivery: 'ray',
            piercing: 'all',
            aoeRadius: null,
        );

        self::assertCount(2, $targets);
        self::assertSame([2, 3], array_map(static fn(LocalActor $a) => $a->getCharacterId(), $targets));
    }

    public function testAoeHitsAllActorsWithinRadiusAroundPoint(): void
    {
        $session  = new LocalSession(worldId: 1, characterId: 1, tileX: 0, tileY: 0, width: 10, height: 10, playerX: 5, playerY: 5);
        $attacker = new LocalActor($session, characterId: 1, role: 'player', x: 5, y: 5);
        $a1       = new LocalActor($session, characterId: 2, role: 'npc', x: 7, y: 7);
        $a2       = new LocalActor($session, characterId: 3, role: 'npc', x: 8, y: 7);

        $targets = (new LocalTargetSelector())->selectTargets(
            attacker: $attacker,
            actors: [$attacker, $a1, $a2],
            aimMode: AimMode::Point,
            direction: null,
            targetX: 7,
            targetY: 7,
            range: 10,
            delivery: 'aoe',
            piercing: null,
            aoeRadius: 1,
        );

        self::assertCount(2, $targets);
        self::assertSame([2, 3], array_map(static fn(LocalActor $a) => $a->getCharacterId(), $targets));
    }

    public function testAoeWithActorAimTreatsAsPoint(): void
    {
        $session  = new LocalSession(worldId: 1, characterId: 1, tileX: 0, tileY: 0, width: 10, height: 10, playerX: 5, playerY: 5);
        $attacker = new LocalActor($session, characterId: 1, role: 'player', x: 5, y: 5);
        $a1       = new LocalActor($session, characterId: 2, role: 'npc', x: 7, y: 7);
        $a2       = new LocalActor($session, characterId: 3, role: 'npc', x: 8, y: 7);

        $targets = (new LocalTargetSelector())->selectTargets(
            attacker: $attacker,
            actors: [$attacker, $a1, $a2],
            aimMode: AimMode::Actor,
            direction: null,
            targetX: 7,
            targetY: 7,
            range: 10,
            delivery: 'aoe',
            piercing: null,
            aoeRadius: 1,
        );

        self::assertCount(2, $targets);
        self::assertSame([2, 3], array_map(static fn(LocalActor $a) => $a->getCharacterId(), $targets));
    }
}
