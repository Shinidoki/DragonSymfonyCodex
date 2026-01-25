<?php

namespace App\Tests\Game\Domain\Goal\Handlers;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\World;
use App\Game\Domain\Goal\GoalContext;
use App\Game\Domain\Goal\Handlers\ParticipateTournamentGoalHandler;
use App\Game\Domain\Map\TileCoord;
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

    public function testSeeksOtherSettlementsIfNoTournamentNearby(): void
    {
        $world = new World('seed-1');
        $world->advanceDays(1); // day = 1
        $character = new Character($world, 'NPC-0001', Race::Human);
        $character->setTilePosition(0, 0);

        $handler = new ParticipateTournamentGoalHandler();
        $context = new GoalContext(
            dojoTiles: [],
            settlementTiles: [new TileCoord(0, 0), new TileCoord(2, 0), new TileCoord(4, 0)],
            settlementsByCoord: [],
            economyCatalog: null,
            events: [],
        );

        $data = ['search_timeout_days' => 3];

        $r = $handler->step($character, $world, $data, $context);
        self::assertSame(DailyActivity::Travel, $r->plan->activity);
        self::assertNotNull($r->plan->travelTarget);
        self::assertNotSame('0:0', sprintf('%d:%d', $r->plan->travelTarget?->x, $r->plan->travelTarget?->y));
        self::assertFalse($r->completed);
        self::assertIsArray($r->data);
        self::assertArrayHasKey('expires_day', $r->data);
    }

    public function testLatchesOntoJoinableTournamentWhenWithinRadius(): void
    {
        $world = new World('seed-1');
        $world->advanceDays(2); // day = 2
        $character = new Character($world, 'NPC-0001', Race::Human);
        $character->setTilePosition(1, 0);

        $event = new CharacterEvent(
            world: $world,
            character: null,
            type: 'tournament_announced',
            day: 1,
            data: [
                'announce_day'           => 1,
                'registration_close_day' => 2,
                'center_x'               => 2,
                'center_y'               => 0,
                'radius'                 => 3,
                'resolve_day'            => 4,
            ],
        );

        $handler = new ParticipateTournamentGoalHandler();
        $context = new GoalContext(
            dojoTiles: [],
            settlementTiles: [new TileCoord(0, 0), new TileCoord(2, 0)],
            settlementsByCoord: [],
            economyCatalog: null,
            events: [$event],
        );

        $data = ['search_timeout_days' => 3];

        $r = $handler->step($character, $world, $data, $context);
        self::assertSame(DailyActivity::Travel, $r->plan->activity);
        self::assertSame(2, $r->plan->travelTarget?->x);
        self::assertSame(0, $r->plan->travelTarget?->y);
        self::assertFalse($r->completed);
        self::assertSame(2, $r->data['center_x'] ?? null);
        self::assertSame(0, $r->data['center_y'] ?? null);
        self::assertSame(4, $r->data['resolve_day'] ?? null);
    }
}
