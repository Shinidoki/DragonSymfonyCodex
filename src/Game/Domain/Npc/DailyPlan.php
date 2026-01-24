<?php

namespace App\Game\Domain\Npc;

use App\Game\Domain\Map\TileCoord;

final readonly class DailyPlan
{
    public function __construct(
        public DailyActivity $activity,
        public ?TileCoord    $travelTarget = null,
    )
    {
    }
}
