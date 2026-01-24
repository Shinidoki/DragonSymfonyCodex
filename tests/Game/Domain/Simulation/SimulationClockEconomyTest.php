<?php

namespace App\Tests\Game\Domain\Simulation;

use App\Entity\Character;
use App\Entity\Settlement;
use App\Entity\World;
use App\Game\Domain\Economy\EconomyCatalog;
use App\Game\Domain\Race;
use App\Game\Domain\Simulation\SimulationClock;
use App\Game\Domain\Stats\Growth\TrainingGrowthService;
use App\Game\Domain\Stats\Growth\TrainingIntensity;
use PHPUnit\Framework\TestCase;

final class SimulationClockEconomyTest extends TestCase
{
    public function testEconomyPaysWagesFromSettlementProduction(): void
    {
        $world = new World('seed-1');
        $world->setMapSize(8, 8);

        $settlement = new Settlement($world, 0, 0);
        $settlement->setProsperity(50);

        $alice = new Character($world, 'Alice', Race::Human);
        $alice->setTilePosition(0, 0);
        $alice->setEmployment('laborer', 0, 0);
        $alice->setWorkFocus(50);

        $bob = new Character($world, 'Bob', Race::Human);
        $bob->setTilePosition(0, 0);
        $bob->setEmployment('merchant', 0, 0);
        $bob->setWorkFocus(100);

        $economy = new EconomyCatalog(
            jobs: [
                'laborer'  => ['label' => 'Laborer', 'wage_weight' => 1, 'work_radius' => 0],
                'merchant' => ['label' => 'Merchant', 'wage_weight' => 2, 'work_radius' => 0],
            ],
            employmentPools: [],
            settlement: [
                'wage_pool_rate' => 1.0,
                'tax_rate'       => 0.0,
                'production'     => [
                    'per_work_unit_base'            => 10,
                    'per_work_unit_prosperity_mult' => 0,
                    'randomness_pct'                => 0.0,
                ],
            ],
            thresholds: [
                'money_low_employed'   => 0,
                'money_low_unemployed' => 0,
            ],
        );

        $clock = new SimulationClock(new TrainingGrowthService());

        $clock->advanceDays(
            world: $world,
            characters: [$alice, $bob],
            days: 1,
            intensity: TrainingIntensity::Normal,
            settlements: [$settlement],
            economyCatalog: $economy,
        );

        // Work units:
        // - Alice: 1 * 0.5 = 0.5
        // - Bob:   2 * 1.0 = 2.0
        // Total: 2.5 => grossProduction = 10 * 2.5 = 25 => wage pool = 25
        self::assertSame(5, $alice->getMoney());
        self::assertSame(20, $bob->getMoney());
        self::assertSame(0, $settlement->getTreasury());
        self::assertSame(1, $settlement->getLastSimDayApplied());
    }
}

