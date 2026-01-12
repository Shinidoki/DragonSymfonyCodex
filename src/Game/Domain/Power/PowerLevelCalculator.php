<?php

namespace App\Game\Domain\Power;

use App\Game\Domain\Stats\CoreAttributes;

final class PowerLevelCalculator
{
    public function calculate(CoreAttributes $attributes): int
    {
        return
            ($attributes->strength * 5)
            + ($attributes->speed * 4)
            + ($attributes->endurance * 4)
            + ($attributes->durability * 4)
            + ($attributes->kiCapacity * 6)
            + ($attributes->kiControl * 6)
            + ($attributes->kiRecovery * 4)
            + ($attributes->focus * 2)
            + ($attributes->discipline * 2)
            + ($attributes->adaptability * 2);
    }
}

