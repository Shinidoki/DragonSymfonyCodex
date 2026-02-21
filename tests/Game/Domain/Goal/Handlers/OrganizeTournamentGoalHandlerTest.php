<?php

namespace App\Tests\Game\Domain\Goal\Handlers;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\Settlement;
use App\Entity\World;
use App\Game\Domain\Economy\EconomyCatalog;
use App\Game\Domain\Goal\GoalContext;
use App\Game\Domain\Goal\Handlers\OrganizeTournamentGoalHandler;
use App\Game\Domain\Map\TileCoord;
use App\Game\Domain\Npc\DailyActivity;
use App\Game\Domain\Race;
use PHPUnit\Framework\TestCase;

final class OrganizeTournamentGoalHandlerTest extends TestCase
{
    public function testMayorTargetsEmploymentSettlementOverNearestSettlement(): void
    {
        $world = new World('seed-1');
        $world->setMapSize(20, 20);

        $home = new Settlement($world, 5, 5);
        $home->addToTreasury(1_000);

        $near = new Settlement($world, 1, 0);
        $near->addToTreasury(1_000);

        $character = new Character($world, 'Mayor', Race::Human);
        $character->setEmployment('mayor', 5, 5);
        $character->setTilePosition(0, 0);

        $handler = new OrganizeTournamentGoalHandler();
        $result  = $handler->step(
            character: $character,
            world: $world,
            data: [],
            context: new GoalContext(
                dojoTiles: [],
                settlementTiles: [new TileCoord(5, 5), new TileCoord(1, 0)],
                settlementsByCoord: ['5:5' => $home, '1:0' => $near],
                economyCatalog: $this->economyCatalog(),
            ),
        );

        self::assertFalse($result->completed);
        self::assertSame(DailyActivity::Travel, $result->plan->activity);
        self::assertSame(5, $result->plan->travelTarget?->x);
        self::assertSame(5, $result->plan->travelTarget?->y);
        self::assertSame(5, $result->data['target_x']);
        self::assertSame(5, $result->data['target_y']);
    }

    public function testMayorIgnoresSettlementTileListAndOnlyTargetsOwnSettlement(): void
    {
        $world = new World('seed-1');
        $world->setMapSize(20, 20);

        $home = new Settlement($world, 5, 5);
        $home->addToTreasury(1_000);

        $other = new Settlement($world, 1, 0);
        $other->addToTreasury(1_000);

        $character = new Character($world, 'Mayor', Race::Human);
        $character->setEmployment('mayor', 5, 5);
        $character->setTilePosition(0, 0);

        $handler = new OrganizeTournamentGoalHandler();
        $result  = $handler->step(
            character: $character,
            world: $world,
            data: ['target_x' => 1, 'target_y' => 0],
            context: new GoalContext(
                dojoTiles: [],
                // Missing the mayor's own settlement tile on purpose.
                settlementTiles: [new TileCoord(1, 0)],
                settlementsByCoord: ['5:5' => $home, '1:0' => $other],
                economyCatalog: $this->economyCatalog(),
            ),
        );

        self::assertFalse($result->completed);
        self::assertSame(DailyActivity::Travel, $result->plan->activity);
        self::assertSame(5, $result->plan->travelTarget?->x);
        self::assertSame(5, $result->plan->travelTarget?->y);
        self::assertSame(5, $result->data['target_x']);
        self::assertSame(5, $result->data['target_y']);
    }

    public function testAppliesSettlementDemandFeedbackToSpendAndRadius(): void
    {
        $world = new World('seed-1');
        $world->advanceDays(1);

        $settlement = new Settlement($world, 3, 7);
        $settlement->addToTreasury(1_000);

        $character = new Character($world, 'Mayor', Race::Human);
        $character->setEmployment('mayor', 3, 7);
        $character->setTilePosition(3, 7);

        $handler = new OrganizeTournamentGoalHandler();
        $result = $handler->step(
            character: $character,
            world: $world,
            data: ['spend' => 200],
            context: new GoalContext(
                dojoTiles: [],
                settlementTiles: [new TileCoord(3, 7)],
                settlementsByCoord: ['3:7' => $settlement],
                economyCatalog: $this->economyCatalog(),
                settlementTournamentFeedbackByCoord: [
                    '3:7' => [
                        'spendMultiplier' => 0.5,
                        'radiusDelta' => 2,
                        'sampleSize' => 5,
                    ],
                ],
            ),
        );

        self::assertTrue($result->completed);
        $eventData = $result->events[0]->getData();
        self::assertSame(100, $eventData['spend']);
        self::assertSame(6, $eventData['radius']);
        self::assertSame(900, $settlement->getTreasury());
    }

    public function testEmitsTournamentAnnouncementEventAndCompletes(): void
    {
        $world = new World('seed-1');
        $world->advanceDays(1); // day = 1

        $settlement = new Settlement($world, 3, 7);
        $settlement->addToTreasury(1_000);

        $character = new Character($world, 'Mayor', Race::Human);
        $character->setTilePosition(3, 7);

        $handler = new OrganizeTournamentGoalHandler();
        $result    = $handler->step(
            character: $character,
            world: $world,
            data: ['spend' => 200],
            context: new GoalContext(
                dojoTiles: [],
                settlementTiles: [new TileCoord(3, 7)],
                settlementsByCoord: ['3:7' => $settlement],
                economyCatalog: $this->economyCatalog(),
            ),
        );

        self::assertTrue($result->completed);
        self::assertCount(1, $result->events);

        $event = $result->events[0];
        self::assertInstanceOf(CharacterEvent::class, $event);
        self::assertSame('tournament_announced', $event->getType());
        self::assertSame(1, $event->getDay());
        self::assertNull($event->getCharacter());

        self::assertSame(800, $settlement->getTreasury());
        self::assertSame(52, $settlement->getProsperity());
        self::assertSame(3, $settlement->getFame());

        self::assertSame([
            'announce_day'           => 1,
            'registration_close_day' => 2,
            'center_x'        => 3,
            'center_y'        => 7,
            'radius'          => 6,
            'spend'           => 200,
            'prize_pool'      => 100,
            'prize_1'         => 50,
            'prize_2'         => 30,
            'prize_3'         => 20,
            'fame_gain'       => 3,
            'prosperity_gain' => 2,
            'resolve_day'     => 3,
        ], $event->getData());
    }

    private function economyCatalog(): EconomyCatalog
    {
        return new EconomyCatalog(
            jobs: [
                'laborer' => ['label' => 'Laborer', 'wage_weight' => 1, 'work_radius' => 1],
            ],
            employmentPools: [],
            settlement: [
                'wage_pool_rate' => 0.5,
                'tax_rate'       => 0.2,
                'production'     => [
                    'per_work_unit_base'            => 0,
                    'per_work_unit_prosperity_mult' => 0,
                    'randomness_pct'                => 0.0,
                ],
            ],
            thresholds: [
                'money_low_employed'   => 0,
                'money_low_unemployed' => 0,
            ],
            tournaments: [
                'min_spend'                      => 50,
                'max_spend_fraction_of_treasury' => 0.30,
                'prize_pool_fraction'            => 0.50,
                'duration_days'                  => 2,
                'radius'                         => [
                    'base'      => 2,
                    'per_spend' => 50,
                    'max'       => 20,
                ],
                'gains'                          => [
                    'fame_base'            => 1,
                    'fame_per_spend'       => 100,
                    'prosperity_base'      => 1,
                    'prosperity_per_spend' => 150,
                    'per_participant_fame' => 0,
                ],
            ],
        );
    }
}
