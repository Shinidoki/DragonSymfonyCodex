<?php

namespace App\Game\Domain\Techniques\Execution;

final readonly class TechniqueExecution
{
    public function __construct(
        public bool $success,
        public int $kiSpent,
        public int $damage,
        public bool $defenderDefeated,
        public string $message,
        public ?string $startedChargingCode = null,
        public ?int $startedChargingTargetActorId = null,
        public ?int $startedChargingTicksRemaining = null,
        public bool $clearedCharging = false,
    )
    {
    }
}

