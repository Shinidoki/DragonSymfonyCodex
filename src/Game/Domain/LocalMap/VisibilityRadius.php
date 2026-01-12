<?php

namespace App\Game\Domain\LocalMap;

final readonly class VisibilityRadius
{
    public function __construct(public int $tiles)
    {
        if ($tiles < 0) {
            throw new \InvalidArgumentException('Visibility radius must be >= 0.');
        }
    }
}

