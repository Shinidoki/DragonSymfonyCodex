<?php

namespace App\Game\Domain\Simulation;

use App\Entity\Character;
use App\Entity\World;
use App\Game\Domain\Stats\Growth\TrainingGrowthService;
use App\Game\Domain\Stats\Growth\TrainingIntensity;

final class SimulationClock
{
    public function __construct(private readonly TrainingGrowthService $trainingGrowth)
    {
    }

    /**
     * @param list<Character> $characters
     */
    public function advanceDays(World $world, array $characters, int $days, TrainingIntensity $intensity): void
    {
        if ($days < 0) {
            throw new \InvalidArgumentException('Days must be >= 0.');
        }

        for ($i = 0; $i < $days; $i++) {
            $world->advanceDays(1);

            foreach ($characters as $character) {
                $character->advanceDays(1);

                $after = $this->trainingGrowth->train($character->getCoreAttributes(), $intensity);
                $character->applyCoreAttributes($after);
            }
        }
    }
}

