<?php

namespace App\Game\Domain\Combat\SimulatedCombat;

final readonly class SimulatedCombatResult
{
    /**
     * @param list<int>    $defeatedCharacterIds
     * @param list<string> $log
     */
    public function __construct(
        public int   $winnerCharacterId,
        public array $defeatedCharacterIds,
        public int   $actions,
        public array $log = [],
    )
    {
        if ($winnerCharacterId <= 0) {
            throw new \InvalidArgumentException('winnerCharacterId must be positive.');
        }
        if ($actions < 0) {
            throw new \InvalidArgumentException('actions must be >= 0.');
        }
    }
}
