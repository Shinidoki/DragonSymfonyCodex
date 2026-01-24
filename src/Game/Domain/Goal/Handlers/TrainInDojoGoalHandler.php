<?php

namespace App\Game\Domain\Goal\Handlers;

use App\Entity\Character;
use App\Entity\World;
use App\Game\Domain\Goal\CurrentGoalHandlerInterface;
use App\Game\Domain\Goal\GoalContext;
use App\Game\Domain\Goal\GoalStepResult;
use App\Game\Domain\Map\TileCoord;
use App\Game\Domain\Npc\DailyActivity;
use App\Game\Domain\Npc\DailyPlan;

final class TrainInDojoGoalHandler implements CurrentGoalHandlerInterface
{
    public function step(Character $character, World $world, array $data, GoalContext $context): GoalStepResult
    {
        $targetDays = $data['target_days'] ?? 7;
        if (!is_int($targetDays) || $targetDays <= 0) {
            throw new \InvalidArgumentException('target_days must be a positive integer.');
        }

        $daysTrained = $data['days_trained'] ?? 0;
        if (!is_int($daysTrained) || $daysTrained < 0) {
            throw new \InvalidArgumentException('days_trained must be an integer >= 0.');
        }

        $current   = new TileCoord($character->getTileX(), $character->getTileY());
        $dojoTiles = $context->dojoTiles;

        $isOnDojo = false;
        foreach ($dojoTiles as $dojo) {
            if ($dojo->x === $current->x && $dojo->y === $current->y) {
                $isOnDojo = true;
                break;
            }
        }

        if (!$isOnDojo && $dojoTiles !== []) {
            $target = $this->nearestDojo($current, $dojoTiles);
            if ($target instanceof TileCoord) {
                return new GoalStepResult(
                    plan: new DailyPlan(DailyActivity::Travel, travelTarget: $target),
                    data: ['target_days' => $targetDays, 'days_trained' => $daysTrained],
                    completed: false,
                );
            }
        }

        $daysTrained++;
        $completed = $daysTrained >= $targetDays;

        return new GoalStepResult(
            plan: new DailyPlan(DailyActivity::Train),
            data: ['target_days' => $targetDays, 'days_trained' => $daysTrained],
            completed: $completed,
        );
    }

    /**
     * @param list<TileCoord> $dojoTiles
     */
    private function nearestDojo(TileCoord $from, array $dojoTiles): ?TileCoord
    {
        $best     = null;
        $bestDist = null;

        foreach ($dojoTiles as $candidate) {
            $dist = abs($candidate->x - $from->x) + abs($candidate->y - $from->y);

            if ($best === null) {
                $best     = $candidate;
                $bestDist = $dist;
                continue;
            }

            if ($dist < $bestDist) {
                $best     = $candidate;
                $bestDist = $dist;
                continue;
            }

            if ($dist === $bestDist) {
                if ($candidate->x < $best->x || ($candidate->x === $best->x && $candidate->y < $best->y)) {
                    $best = $candidate;
                }
            }
        }

        return $best;
    }
}

