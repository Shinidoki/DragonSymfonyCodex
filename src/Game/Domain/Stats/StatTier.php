<?php

namespace App\Game\Domain\Stats;

enum StatTier: int
{
    case Weak = 1;
    case Average = 2;
    case Trained = 3;
    case Strong = 4;
    case Exceptional = 5;
    case Elite = 6;
    case Legendary = 7;

    public static function fromValue(int $value): self
    {
        return match (true) {
            $value < 5 => self::Weak,
            $value < 15 => self::Average,
            $value < 30 => self::Trained,
            $value < 60 => self::Strong,
            $value < 120 => self::Exceptional,
            $value < 250 => self::Elite,
            default => self::Legendary,
        };
    }

    public function label(): string
    {
        return $this->name;
    }
}

