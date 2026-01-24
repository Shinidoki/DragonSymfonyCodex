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

final class ParticipateTournamentGoalHandler implements CurrentGoalHandlerInterface
{
    public function step(Character $character, World $world, array $data, GoalContext $context): GoalStepResult
    {
        $x = $data['center_x'] ?? null;
        $y = $data['center_y'] ?? null;

        if (!is_int($x) || !is_int($y) || $x < 0 || $y < 0) {
            throw new \InvalidArgumentException('center_x and center_y must be integers >= 0.');
        }

        if ($character->getTileX() === $x && $character->getTileY() === $y) {
            return new GoalStepResult(
                plan: new DailyPlan(DailyActivity::Rest),
                data: $data,
                completed: true,
            );
        }

        return new GoalStepResult(
            plan: new DailyPlan(DailyActivity::Travel, travelTarget: new TileCoord($x, $y)),
            data: $data,
            completed: false,
        );
    }
}

