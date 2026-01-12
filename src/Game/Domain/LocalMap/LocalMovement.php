<?php

namespace App\Game\Domain\LocalMap;

final class LocalMovement
{
    public function move(LocalCoord $current, Direction $direction, LocalMapSize $size): LocalCoord
    {
        $dx = 0;
        $dy = 0;

        switch ($direction) {
            case Direction::North:
                $dy = -1;
                break;
            case Direction::South:
                $dy = 1;
                break;
            case Direction::East:
                $dx = 1;
                break;
            case Direction::West:
                $dx = -1;
                break;
        }

        $x = $current->x + $dx;
        $y = $current->y + $dy;

        $x = max(0, min($x, $size->width - 1));
        $y = max(0, min($y, $size->height - 1));

        return new LocalCoord($x, $y);
    }
}

