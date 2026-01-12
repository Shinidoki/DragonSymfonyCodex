<?php

namespace App\Game\Domain\Map;

final readonly class TileCoord
{
    public function __construct(public int $x, public int $y)
    {
        if ($x < 0 || $y < 0) {
            throw new \InvalidArgumentException('Tile coordinates must be >= 0.');
        }
    }
}

