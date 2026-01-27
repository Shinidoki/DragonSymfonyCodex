<?php

namespace App\Game\Domain\Combat\SimulatedCombat;

final readonly class CombatRules
{
    public function __construct(
        public bool $allowFriendlyFire,
        public int  $maxActions = 10_000,
        public bool $allowTransform = true,
    )
    {
        if ($maxActions <= 0) {
            throw new \InvalidArgumentException('maxActions must be positive.');
        }
    }
}
