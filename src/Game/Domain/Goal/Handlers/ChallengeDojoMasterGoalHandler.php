<?php

namespace App\Game\Domain\Goal\Handlers;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\World;
use App\Game\Domain\Goal\CurrentGoalHandlerInterface;
use App\Game\Domain\Goal\GoalContext;
use App\Game\Domain\Goal\GoalStepResult;
use App\Game\Domain\Map\TileCoord;
use App\Game\Domain\Npc\DailyActivity;
use App\Game\Domain\Npc\DailyPlan;

final class ChallengeDojoMasterGoalHandler implements CurrentGoalHandlerInterface
{
    public function step(Character $character, World $world, array $data, GoalContext $context): GoalStepResult
    {
        $dojoTiles = $context->dojoTiles;
        if ($dojoTiles === []) {
            return new GoalStepResult(plan: new DailyPlan(DailyActivity::Rest), data: $data, completed: false);
        }

        $current = new TileCoord($character->getTileX(), $character->getTileY());
        $target  = $this->nearest($current, $dojoTiles);
        if (!$target instanceof TileCoord) {
            return new GoalStepResult(plan: new DailyPlan(DailyActivity::Rest), data: $data, completed: false);
        }

        if ($current->x !== $target->x || $current->y !== $target->y) {
            return new GoalStepResult(
                plan: new DailyPlan(DailyActivity::Travel, travelTarget: $target),
                data: $data,
                completed: false,
            );
        }

        $event = new CharacterEvent(
            world: $world,
            character: $character,
            type: 'dojo_challenge_requested',
            day: $world->getCurrentDay(),
            data: [
                'settlement_x' => $current->x,
                'settlement_y' => $current->y,
            ],
        );

        return new GoalStepResult(
            plan: new DailyPlan(DailyActivity::Rest),
            data: $data,
            completed: true,
            events: [$event],
        );
    }

    /**
     * @param list<TileCoord> $tiles
     */
    private function nearest(TileCoord $from, array $tiles): ?TileCoord
    {
        $best     = null;
        $bestDist = null;

        foreach ($tiles as $tile) {
            $dist = abs($tile->x - $from->x) + abs($tile->y - $from->y);
            if ($best === null || $bestDist === null || $dist < $bestDist) {
                $best     = $tile;
                $bestDist = $dist;
                continue;
            }

            if ($dist === $bestDist) {
                if ($tile->x < $best->x || ($tile->x === $best->x && $tile->y < $best->y)) {
                    $best = $tile;
                }
            }
        }

        return $best;
    }
}

