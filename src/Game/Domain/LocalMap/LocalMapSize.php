<?php

namespace App\Game\Domain\LocalMap;

final readonly class LocalMapSize
{
    public function __construct(public int $width, public int $height)
    {
        if ($width <= 0 || $height <= 0) {
            throw new \InvalidArgumentException('Local map size must be positive.');
        }
    }
}

