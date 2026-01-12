<?php

namespace App\Game\Domain\Stats\Growth;

use App\Game\Domain\Stats\CoreAttributes;

final class TrainingGrowthService
{
    public function train(CoreAttributes $before, TrainingIntensity $intensity): CoreAttributes
    {
        return $before->withDelta($this->deltaFor($intensity));
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
}

