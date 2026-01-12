<?php

namespace App\Game\Domain\Npc;

use App\Entity\Character;

final class DailyPlanner
{
    public function planFor(Character $character): DailyPlan
    {
        if ($character->hasTravelTarget()) {
            return new DailyPlan(DailyActivity::Travel);
        }

        return new DailyPlan(DailyActivity::Train);
    }
}

