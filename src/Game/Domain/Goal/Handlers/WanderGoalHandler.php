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

final class WanderGoalHandler implements CurrentGoalHandlerInterface
{
    public function step(Character $character, World $world, array $data, GoalContext $context): GoalStepResult
    {
        $width  = $world->getWidth();
        $height = $world->getHeight();

        if ($width <= 0 || $height <= 0) {
            return new GoalStepResult(
                plan: new DailyPlan(DailyActivity::Rest),
                data: $data,
                completed: false,
            );
        }

        $targetX = $data['target_x'] ?? null;
        $targetY = $data['target_y'] ?? null;

        if (!is_int($targetX) || !is_int($targetY) || $targetX < 0 || $targetY < 0 || $targetX >= $width || $targetY >= $height) {
            $targetX = random_int(0, $width - 1);
            $targetY = random_int(0, $height - 1);

            if ($targetX === $character->getTileX() && $targetY === $character->getTileY()) {
                $targetX = ($targetX + 1) % $width;
            }
        }

        $data['target_x'] = $targetX;
        $data['target_y'] = $targetY;

        if ($character->getTileX() === $targetX && $character->getTileY() === $targetY) {
            return new GoalStepResult(
                plan: new DailyPlan(DailyActivity::Rest),
                data: $data,
                completed: true,
            );
        }

        return new GoalStepResult(
            plan: new DailyPlan(DailyActivity::Travel, travelTarget: new TileCoord($targetX, $targetY)),
            data: $data,
            completed: false,
        );
    }
}

