<?php

namespace App\Tests\Game\Domain\Goal\Handlers;

use App\Entity\Character;
use App\Entity\World;
use App\Game\Domain\Goal\GoalContext;
use App\Game\Domain\Goal\Handlers\FindJobGoalHandler;
use App\Game\Domain\Map\TileCoord;
use App\Game\Domain\Npc\DailyActivity;
use App\Game\Domain\Race;
use PHPUnit\Framework\TestCase;

final class FindJobGoalHandlerTest extends TestCase
{
    public function testTravelsToNearestSettlementThenCompletes(): void
    {
        $world = new World('seed-1');
        $world->setMapSize(8, 8);

        $character = new Character($world, 'NPC', Race::Human);
        $character->setTilePosition(0, 0);

        $handler = new FindJobGoalHandler();
        $context = new GoalContext(settlementTiles: [new TileCoord(3, 0), new TileCoord(0, 5)]);

        $step1 = $handler->step($character, $world, [], $context);
        self::assertSame(DailyActivity::Travel, $step1->plan->activity);
        self::assertFalse($step1->completed);
        self::assertSame(['target_x' => 3, 'target_y' => 0], $step1->data);

        $character->setTilePosition(3, 0);

        $step2 = $handler->step($character, $world, $step1->data, $context);
        self::assertSame(DailyActivity::Rest, $step2->plan->activity);
        self::assertTrue($step2->completed);
        self::assertSame(['target_x' => 3, 'target_y' => 0], $step2->data);
    }

    public function testAlreadyEmployedCompletesImmediately(): void
    {
        $world = new World('seed-1');
        $world->setMapSize(8, 8);

        $character = new Character($world, 'NPC', Race::Human);
        $character->setTilePosition(0, 0);
        $character->setEmployment('laborer', 0, 0);

        $handler = new FindJobGoalHandler();
        $context = new GoalContext(settlementTiles: [new TileCoord(3, 0)]);

        $step = $handler->step($character, $world, [], $context);
        self::assertSame(DailyActivity::Rest, $step->plan->activity);
        self::assertTrue($step->completed);
    }
}

