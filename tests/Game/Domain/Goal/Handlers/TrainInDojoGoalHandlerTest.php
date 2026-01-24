<?php

namespace App\Tests\Game\Domain\Goal\Handlers;

use App\Entity\Character;
use App\Entity\World;
use App\Game\Domain\Goal\GoalContext;
use App\Game\Domain\Goal\Handlers\TrainInDojoGoalHandler;
use App\Game\Domain\Map\TileCoord;
use App\Game\Domain\Npc\DailyActivity;
use App\Game\Domain\Race;
use PHPUnit\Framework\TestCase;

final class TrainInDojoGoalHandlerTest extends TestCase
{
    public function testTravelsToNearestDojoThenTrainsUntilComplete(): void
    {
        $world     = new World('seed-1');
        $character = new Character($world, 'NPC-0001', Race::Human);
        $character->setTilePosition(0, 0);

        $handler = new TrainInDojoGoalHandler();
        $context = new GoalContext(dojoTiles: [new TileCoord(2, 0)]);

        $r1 = $handler->step($character, $world, ['target_days' => 2], $context);
        self::assertSame(DailyActivity::Travel, $r1->plan->activity);
        self::assertSame(2, $r1->plan->travelTarget?->x);
        self::assertSame(0, $r1->plan->travelTarget?->y);
        self::assertFalse($r1->completed);

        $character->setTilePosition(2, 0);

        $r2 = $handler->step($character, $world, $r1->data, $context);
        self::assertSame(DailyActivity::Train, $r2->plan->activity);
        self::assertSame(1, $r2->data['days_trained']);
        self::assertFalse($r2->completed);

        $r3 = $handler->step($character, $world, $r2->data, $context);
        self::assertSame(DailyActivity::Train, $r3->plan->activity);
        self::assertSame(2, $r3->data['days_trained']);
        self::assertTrue($r3->completed);
    }
}

