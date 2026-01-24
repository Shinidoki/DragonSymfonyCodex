<?php

namespace App\Game\Domain\Goal\Handlers;

use App\Entity\Character;
use App\Entity\World;
use App\Game\Domain\Goal\CurrentGoalHandlerInterface;
use App\Game\Domain\Goal\GoalContext;
use App\Game\Domain\Goal\GoalStepResult;
use App\Game\Domain\Npc\DailyActivity;
use App\Game\Domain\Npc\DailyPlan;

final class EarnMoneyGoalHandler implements CurrentGoalHandlerInterface
{
    public function step(Character $character, World $world, array $data, GoalContext $context): GoalStepResult
    {
        // Simulation MVP: earning money is modeled as a stable "rest/work" daily state.
        // It does not complete automatically until an economy is implemented.
        return new GoalStepResult(
            plan: new DailyPlan(DailyActivity::Rest),
            data: $data,
            completed: false,
        );
    }
}

