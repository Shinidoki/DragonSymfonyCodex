<?php

namespace App\Game\Domain\Transformations;

use App\Game\Domain\Stats\CoreAttributes;

final class TransformationService
{
    public function activate(TransformationState $state, Transformation $transformation): TransformationState
    {
        if ($state->exhaustionDaysRemaining > 0) {
            throw new \RuntimeException('Cannot transform while exhausted.');
        }
        if ($state->active !== null) {
            throw new \RuntimeException('Already transformed.');
        }

        return $state->withActive($transformation);
    }

    public function deactivate(TransformationState $state): TransformationState
    {
        if ($state->active === null) {
            return $state;
        }

        $safeTicks    = $state->active->safeTicks();
        $overuseTicks = max(0, $state->activeTicks - $safeTicks);

        $exhaustionDays = $overuseTicks > 0 ? 1 : 0;

        return $state->deactivateWithExhaustion($exhaustionDays);
    }

    public function advanceDay(TransformationState $state): TransformationState
    {
        return $state->recoverOneDay();
    }

    public function effectiveAttributes(CoreAttributes $base, TransformationState $state): CoreAttributes
    {
        if ($state->active !== null) {
            return $this->scale($base, $state->active->multiplier());
        }

        if ($state->exhaustionDaysRemaining > 0) {
            return $this->scale($base, 0.8);
        }

        return $base;
    }

    private function scale(CoreAttributes $base, float $multiplier): CoreAttributes
    {
        $scale = static fn(int $value): int => max(0, (int)floor($value * $multiplier));

        return new CoreAttributes(
            strength: $scale($base->strength),
            speed: $scale($base->speed),
            endurance: $scale($base->endurance),
            durability: $scale($base->durability),
            kiCapacity: $scale($base->kiCapacity),
            kiControl: $scale($base->kiControl),
            kiRecovery: $scale($base->kiRecovery),
            focus: $scale($base->focus),
            discipline: $scale($base->discipline),
            adaptability: $scale($base->adaptability),
        );
    }
}

