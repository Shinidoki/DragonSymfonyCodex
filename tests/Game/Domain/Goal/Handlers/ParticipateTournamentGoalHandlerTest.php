<?php

namespace App\Tests\Game\Domain\Goal\Handlers;

use App\Entity\Character;
use App\Entity\World;
use App\Game\Domain\Goal\GoalContext;
use App\Game\Domain\Goal\Handlers\ParticipateTournamentGoalHandler;
use App\Game\Domain\Npc\DailyActivity;
use App\Game\Domain\Race;
use PHPUnit\Framework\TestCase;

final class ParticipateTournamentGoalHandlerTest extends TestCase
{
    public function testTravelsToTournamentCenterThenWaitsUntilResolvedDay(): void
    {
        $world     = new World('seed-1');
        $world->advanceDays(1); // day = 1 (matches SimulationClock timing)
        $character = new Character($world, 'NPC-0001', Race::Human);
        $character->setTilePosition(0, 0);

        $handler = new ParticipateTournamentGoalHandler();
        $context = new GoalContext();

        $data = ['center_x' => 1, 'center_y' => 0, 'resolve_day' => 3];

        $r1 = $handler->step($character, $world, $data, $context);
        self::assertSame(DailyActivity::Travel, $r1->plan->activity);
        self::assertSame(1, $r1->plan->travelTarget?->x);
        self::assertSame(0, $r1->plan->travelTarget?->y);
        self::assertFalse($r1->completed);

        $character->setTilePosition(1, 0);

        $r2 = $handler->step($character, $world, $data, $context);
        self::assertSame(DailyActivity::Rest, $r2->plan->activity);
        self::assertFalse($r2->completed);
    }

    public function testCompletesIfWorldDayPassedResolveDay(): void
    {
        $world = new World('seed-1');
        $world->advanceDays(5); // day = 5
        $character = new Character($world, 'NPC-0001', Race::Human);
        $character->setTilePosition(1, 0);

        $handler = new ParticipateTournamentGoalHandler();
        $context = new GoalContext();

        $data = ['center_x' => 1, 'center_y' => 0, 'resolve_day' => 3];

        $r = $handler->step($character, $world, $data, $context);
        self::assertSame(DailyActivity::Rest, $r->plan->activity);
        self::assertTrue($r->completed);
    }
}
