<?php

namespace App\Game\Domain\Techniques\Execution;

use App\Game\Domain\Techniques\TechniqueType;

final class ChargedExecutor extends BlastExecutor
{
    public function supports(TechniqueType $type): bool
    {
        return $type === TechniqueType::Charged;
    }

    public function execute(TechniqueContext $context): TechniqueExecution
    {
        $config = $context->definition->getConfig();
        $chargeTicks = max(0, (int)($config['chargeTicks'] ?? 1));

        // If already charging this technique, the caller decides when to release.
        // This executor only starts charging.
        return new TechniqueExecution(
            success: false,
            kiSpent: 0,
            damage: 0,
            defenderDefeated: false,
            message: sprintf('%s begins charging %s.', $context->attackerCharacter->getName(), $context->definition->getName()),
            startedChargingCode: $context->definition->getCode(),
            startedChargingTargetActorId: (int)$context->defenderActor->getId(),
            startedChargingTicksRemaining: $chargeTicks,
            clearedCharging: false,
        );
    }
}

