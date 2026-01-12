<?php

namespace App\Game\Domain\Training;

enum TrainingContext: string
{
    case Wilderness = 'wilderness';
    case Dojo = 'dojo';
    case Mentor = 'mentor';

    public function multiplier(): float
    {
        return match ($this) {
            self::Wilderness => 1.0,
            self::Dojo => 1.25,
            self::Mentor => 1.5,
        };
    }
}

