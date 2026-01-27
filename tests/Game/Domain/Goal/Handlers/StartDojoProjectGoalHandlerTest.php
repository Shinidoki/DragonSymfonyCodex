<?php

namespace App\Tests\Game\Domain\Goal\Handlers;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\World;
use App\Game\Domain\Goal\GoalContext;
use App\Game\Domain\Goal\Handlers\StartDojoProjectGoalHandler;
use App\Game\Domain\Npc\DailyActivity;
use App\Game\Domain\Race;
use PHPUnit\Framework\TestCase;

final class StartDojoProjectGoalHandlerTest extends TestCase
{
    public function testTravelsToEmploymentSettlementWhenNotAtSettlement(): void
    {
        $world = new World('seed-1');
        $world->advanceDays(1); // day = 1

        $character = new Character($world, 'Mayor', Race::Human);
        $character->setEmployment('mayor', 5, 5);
        $character->setTilePosition(0, 0);

        $handler = new StartDojoProjectGoalHandler();

        $result = $handler->step(
            character: $character,
            world: $world,
            data: [],
            context: new GoalContext(),
        );

        self::assertSame(DailyActivity::Travel, $result->plan->activity);
        self::assertSame(5, $result->plan->travelTarget?->x);
        self::assertSame(5, $result->plan->travelTarget?->y);
        self::assertFalse($result->completed);
    }

    public function testEmitsProjectStartRequestWhenAtSettlementAndNoProjectExists(): void
    {
        $world = new World('seed-1');
        $world->advanceDays(1); // day = 1

        $character = new Character($world, 'Mayor', Race::Human);
        $character->setEmployment('mayor', 5, 5);
        $character->setTilePosition(5, 5);

        $handler = new StartDojoProjectGoalHandler();

        $result = $handler->step(
            character: $character,
            world: $world,
            data: [],
            context: new GoalContext(
                settlementBuildingsByCoord: ['5:5' => ['dojo' => 0]],
                activeSettlementProjectsByCoord: [],
            ),
        );

        self::assertTrue($result->completed);
        self::assertSame(DailyActivity::Rest, $result->plan->activity);
        self::assertCount(1, $result->events);

        $event = $result->events[0];
        self::assertInstanceOf(CharacterEvent::class, $event);
        self::assertSame('settlement_project_start_requested', $event->getType());
        self::assertNull($event->getCharacter());
        self::assertSame(1, $event->getDay());
        self::assertSame([
            'settlement_x'  => 5,
            'settlement_y'  => 5,
            'building_code' => 'dojo',
            'target_level'  => 1,
        ], $event->getData());
    }
}

