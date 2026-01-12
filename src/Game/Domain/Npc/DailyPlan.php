<?php

namespace App\Game\Domain\Npc;

final readonly class DailyPlan
{
    public function __construct(public DailyActivity $activity)
    {
    }
}

