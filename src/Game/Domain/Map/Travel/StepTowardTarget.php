<?php

namespace App\Game\Domain\Map\Travel;

use App\Game\Domain\Map\TileCoord;

final class StepTowardTarget
{
    public function step(TileCoord $current, TileCoord $target): TileCoord
    {
        if ($current->x === $target->x && $current->y === $target->y) {
            return $current;
        }

        if ($current->x !== $target->x) {
            $dx = $current->x < $target->x ? 1 : -1;
            return new TileCoord($current->x + $dx, $current->y);
        }

        $dy = $current->y < $target->y ? 1 : -1;
        return new TileCoord($current->x, $current->y + $dy);
    }
}

