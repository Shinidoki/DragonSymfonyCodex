<?php

namespace App\Tests\Game\Domain\Simulation;

use App\Entity\Character;
use App\Entity\World;
use App\Game\Domain\Map\TileCoord;
use App\Game\Domain\Race;
use App\Game\Domain\Simulation\SimulationClock;
use App\Game\Domain\Stats\Growth\TrainingGrowthService;
use App\Game\Domain\Stats\Growth\TrainingIntensity;
use PHPUnit\Framework\TestCase;

final class DojoLevelTrainingMultiplierTest extends TestCase
{
    public function testUsesDojoLevelMultiplierWhenProvided(): void
    {
        $world = new World('seed-1');

        $character = new Character($world, 'NPC', Race::Human);
        $character->setTilePosition(0, 0);

        $clock = new SimulationClock(new TrainingGrowthService());

        $before = $character->getStrength();

        $clock->advanceDays(
            world: $world,
            characters: [$character],
            days: 1,
            intensity: TrainingIntensity::Hard,
            dojoTiles: [new TileCoord(0, 0)],
            dojoTrainingMultipliersByCoord: ['0:0' => 1.35],
        );

        // Hard intensity delta=3; ceil(3 * 1.35) = 5
        self::assertSame($before + 5, $character->getStrength());
    }

    public function testFallsBackToDefaultDojoMultiplierWhenNoLevelMapProvided(): void
    {
        $world = new World('seed-1');

        $character = new Character($world, 'NPC', Race::Human);
        $character->setTilePosition(0, 0);

        $clock = new SimulationClock(new TrainingGrowthService());

        $before = $character->getStrength();

        $clock->advanceDays(
            world: $world,
            characters: [$character],
            days: 1,
            intensity: TrainingIntensity::Hard,
            dojoTiles: [new TileCoord(0, 0)],
        );

        // Hard intensity delta=3; ceil(3 * 1.25) = 4 (TrainingContext::Dojo multiplier)
        self::assertSame($before + 4, $character->getStrength());
    }
}

