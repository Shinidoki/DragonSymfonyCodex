<?php

namespace App\Game\Domain\Goal;

use App\Game\Domain\Npc\DailyPlan;

final readonly class GoalStepResult
{
    /**
     * @param array<string,mixed> $data
     * @param list<\App\Entity\CharacterEvent> $events
     */
    public function __construct(
        public DailyPlan $plan,
        public array     $data,
        public bool      $completed,
        public array $events = [],
    )
    {
    }
}
