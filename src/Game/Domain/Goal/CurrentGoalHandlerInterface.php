<?php

namespace App\Game\Domain\Goal;

use App\Entity\Character;
use App\Entity\World;

interface CurrentGoalHandlerInterface
{
    /**
     * @param array<string,mixed> $data
     */
    public function step(Character $character, World $world, array $data, GoalContext $context): GoalStepResult;
}

