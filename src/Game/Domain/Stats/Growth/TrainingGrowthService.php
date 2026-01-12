<?php

namespace App\Game\Domain\Stats\Growth;

use App\Game\Domain\Stats\CoreAttributes;

final class TrainingGrowthService
{
    public function train(CoreAttributes $before, TrainingIntensity $intensity): CoreAttributes
    {
        return $this->trainWithMultiplier($before, $intensity, 1.0);
    }

    public function trainWithMultiplier(CoreAttributes $before, TrainingIntensity $intensity, float $multiplier): CoreAttributes
    {
        if ($multiplier <= 0) {
            throw new \InvalidArgumentException('Multiplier must be > 0.');
        }

        $delta  = $this->deltaFor($intensity);
        $scaled = $this->scale($delta, $multiplier);

        return $before->withDelta($scaled);
    }

    public function trainWithResult(CoreAttributes $before, TrainingIntensity $intensity): TrainingResult
    {
        $after = $this->train($before, $intensity);

        return new TrainingResult($before, $after, messages: []);
    }

    private function deltaFor(TrainingIntensity $intensity): CoreAttributes
    {
        $delta = match ($intensity) {
            TrainingIntensity::Light => 1,
            TrainingIntensity::Normal => 2,
            TrainingIntensity::Hard => 3,
            TrainingIntensity::Extreme => 5,
        };

        return new CoreAttributes(
            strength: $delta,
            speed: $delta,
            endurance: $delta,
            durability: $delta,
            kiCapacity: $delta,
            kiControl: $delta,
            kiRecovery: $delta,
            focus: $delta,
            discipline: $delta,
            adaptability: $delta,
        );
    }

    private function scale(CoreAttributes $delta, float $multiplier): CoreAttributes
    {
        $scale = static fn(int $value): int => max(0, (int)ceil($value * $multiplier));

        return new CoreAttributes(
            strength: $scale($delta->strength),
            speed: $scale($delta->speed),
            endurance: $scale($delta->endurance),
            durability: $scale($delta->durability),
            kiCapacity: $scale($delta->kiCapacity),
            kiControl: $scale($delta->kiControl),
            kiRecovery: $scale($delta->kiRecovery),
            focus: $scale($delta->focus),
            discipline: $scale($delta->discipline),
            adaptability: $scale($delta->adaptability),
        );
    }
}
