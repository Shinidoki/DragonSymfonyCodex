<?php

namespace App\Game\Domain\Simulation;

use App\Entity\Character;
use App\Entity\World;
use App\Game\Domain\Map\TileCoord;
use App\Game\Domain\Map\Travel\StepTowardTarget;
use App\Game\Domain\Npc\DailyActivity;
use App\Game\Domain\Npc\DailyPlanner;
use App\Game\Domain\Stats\Growth\TrainingGrowthService;
use App\Game\Domain\Stats\Growth\TrainingIntensity;

final class SimulationClock
{
    public function __construct(
        private readonly TrainingGrowthService $trainingGrowth,
        private readonly ?DailyPlanner         $dailyPlanner = null,
        private readonly ?StepTowardTarget     $stepTowardTarget = null,
    )
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

        $planner = $this->dailyPlanner ?? new DailyPlanner();
        $stepper = $this->stepTowardTarget ?? new StepTowardTarget();

        for ($i = 0; $i < $days; $i++) {
            $world->advanceDays(1);

            foreach ($characters as $character) {
                $character->advanceDays(1);

                $plan = $planner->planFor($character);

                if ($plan->activity === DailyActivity::Train) {
                    $after = $this->trainingGrowth->train($character->getCoreAttributes(), $intensity);
                    $character->applyCoreAttributes($after);
                    continue;
                }

                if ($plan->activity === DailyActivity::Travel && $character->hasTravelTarget()) {
                    $current = new TileCoord($character->getTileX(), $character->getTileY());
                    $target  = new TileCoord((int)$character->getTargetTileX(), (int)$character->getTargetTileY());
                    $next    = $stepper->step($current, $target);
                    $character->setTilePosition($next->x, $next->y);

                    if ($next->x === $target->x && $next->y === $target->y) {
                        $character->clearTravelTarget();
                    }
                }
            }
        }
    }
}
