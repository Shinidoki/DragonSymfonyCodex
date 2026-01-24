<?php

namespace App\Game\Domain\Goal\Handlers;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\World;
use App\Game\Domain\Goal\CurrentGoalHandlerInterface;
use App\Game\Domain\Goal\GoalContext;
use App\Game\Domain\Goal\GoalStepResult;
use App\Game\Domain\Npc\DailyActivity;
use App\Game\Domain\Npc\DailyPlan;

final class OrganizeTournamentGoalHandler implements CurrentGoalHandlerInterface
{
    public function step(Character $character, World $world, array $data, GoalContext $context): GoalStepResult
    {
        $radius = $data['radius'] ?? 5;
        if (!is_int($radius) || $radius < 0) {
            throw new \InvalidArgumentException('radius must be an integer >= 0.');
        }

        $event = new CharacterEvent(
            world: $world,
            character: null,
            type: 'tournament_announced',
            day: $world->getCurrentDay(),
            data: [
                'center_x' => $character->getTileX(),
                'center_y' => $character->getTileY(),
                'radius'   => $radius,
            ],
        );

        return new GoalStepResult(
            plan: new DailyPlan(DailyActivity::Rest),
            data: $data,
            completed: true,
            events: [$event],
        );
    }
}

