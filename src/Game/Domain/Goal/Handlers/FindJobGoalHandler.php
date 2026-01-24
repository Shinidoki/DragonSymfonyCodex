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

final class FindJobGoalHandler implements CurrentGoalHandlerInterface
{
    public function step(Character $character, World $world, array $data, GoalContext $context): GoalStepResult
    {
        if ($character->isEmployed()) {
            return new GoalStepResult(
                plan: new DailyPlan(DailyActivity::Rest),
                data: $data,
                completed: true,
            );
        }

        $settlements = $context->settlementTiles;
        if ($settlements === []) {
            return new GoalStepResult(
                plan: new DailyPlan(DailyActivity::Rest),
                data: $data,
                completed: false,
            );
        }

        $width  = $world->getWidth();
        $height = $world->getHeight();

        $targetX = $data['target_x'] ?? null;
        $targetY = $data['target_y'] ?? null;

        $target = null;
        if (is_int($targetX) && is_int($targetY)) {
            if ($targetX >= 0 && $targetY >= 0 && ($width <= 0 || $targetX < $width) && ($height <= 0 || $targetY < $height)) {
                foreach ($settlements as $s) {
                    if ($s->x === $targetX && $s->y === $targetY) {
                        $target = $s;
                        break;
                    }
                }
            }
        }

        if (!$target instanceof TileCoord) {
            $target = $this->nearestSettlement(new TileCoord($character->getTileX(), $character->getTileY()), $settlements);
        }

        if (!$target instanceof TileCoord) {
            return new GoalStepResult(
                plan: new DailyPlan(DailyActivity::Rest),
                data: $data,
                completed: false,
            );
        }

        $data['target_x'] = $target->x;
        $data['target_y'] = $target->y;

        if ($character->getTileX() === $target->x && $character->getTileY() === $target->y) {
            return new GoalStepResult(
                plan: new DailyPlan(DailyActivity::Rest),
                data: $data,
                completed: true,
            );
        }

        return new GoalStepResult(
            plan: new DailyPlan(DailyActivity::Travel, travelTarget: $target),
            data: $data,
            completed: false,
        );
    }

    /**
     * @param list<TileCoord> $settlements
     */
    private function nearestSettlement(TileCoord $from, array $settlements): ?TileCoord
    {
        $best     = null;
        $bestDist = null;

        foreach ($settlements as $candidate) {
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

