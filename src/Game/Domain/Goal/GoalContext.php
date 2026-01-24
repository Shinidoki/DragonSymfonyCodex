<?php

namespace App\Game\Domain\Goal;

use App\Game\Domain\Map\TileCoord;

final readonly class GoalContext
{
    /**
     * @param list<TileCoord> $dojoTiles
     */
    public function __construct(public array $dojoTiles = [])
    {
    }
}

