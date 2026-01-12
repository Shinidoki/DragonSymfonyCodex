<?php

namespace App\Game\Domain\LocalMap;

final readonly class LocalCoord
{
    public function __construct(public int $x, public int $y)
    {
        if ($x < 0 || $y < 0) {
            throw new \InvalidArgumentException('Local coordinates must be >= 0.');
        }
    }
}

