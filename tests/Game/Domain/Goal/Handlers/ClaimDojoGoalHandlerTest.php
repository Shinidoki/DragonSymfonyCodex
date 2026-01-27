<?php

namespace App\Tests\Game\Domain\Goal\Handlers;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\World;
use App\Game\Domain\Goal\GoalContext;
use App\Game\Domain\Goal\Handlers\ClaimDojoGoalHandler;
use App\Game\Domain\Map\TileCoord;
use App\Game\Domain\Npc\DailyActivity;
use App\Game\Domain\Race;
use PHPUnit\Framework\TestCase;

final class ClaimDojoGoalHandlerTest extends TestCase
{
    public function testTravelsToNearestDojoThenEmitsClaimRequest(): void
    {
        $world     = new World('seed-1');
        $character = new Character($world, 'NPC', Race::Human);
        $character->setTilePosition(0, 0);

        $handler = new ClaimDojoGoalHandler();
        $context = new GoalContext(dojoTiles: [new TileCoord(2, 0)]);

        $r1 = $handler->step($character, $world, [], $context);
        self::assertSame(DailyActivity::Travel, $r1->plan->activity);
        self::assertSame(2, $r1->plan->travelTarget?->x);
        self::assertSame(0, $r1->plan->travelTarget?->y);
        self::assertFalse($r1->completed);

        $character->setTilePosition(2, 0);

        $r2 = $handler->step($character, $world, $r1->data, $context);
        self::assertTrue($r2->completed);
        self::assertCount(1, $r2->events);

        $event = $r2->events[0];
        self::assertInstanceOf(CharacterEvent::class, $event);
        self::assertSame('dojo_claim_requested', $event->getType());
        self::assertSame(['settlement_x' => 2, 'settlement_y' => 0], $event->getData());
        self::assertSame($character, $event->getCharacter());
    }
}

