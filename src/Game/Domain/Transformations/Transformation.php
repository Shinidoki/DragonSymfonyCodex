<?php

namespace App\Game\Domain\Transformations;

enum Transformation: string
{
    case SuperSaiyan = 'super_saiyan';

    public function multiplier(): float
    {
        return match ($this) {
            self::SuperSaiyan => 2.0,
        };
    }

    public function safeTicks(): int
    {
        return match ($this) {
            self::SuperSaiyan => 3,
        };
    }
}

