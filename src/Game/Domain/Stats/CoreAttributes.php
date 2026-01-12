<?php

namespace App\Game\Domain\Stats;

final readonly class CoreAttributes
{
    public function __construct(
        public int $strength,
        public int $speed,
        public int $endurance,
        public int $durability,
        public int $kiCapacity,
        public int $kiControl,
        public int $kiRecovery,
        public int $focus,
        public int $discipline,
        public int $adaptability,
    )
    {
    }

    public static function baseline(): self
    {
        return new self(
            strength: 1,
            speed: 1,
            endurance: 1,
            durability: 1,
            kiCapacity: 1,
            kiControl: 1,
            kiRecovery: 1,
            focus: 1,
            discipline: 1,
            adaptability: 1,
        );
    }

    public function withDelta(self $delta): self
    {
        return new self(
            strength: $this->strength + $delta->strength,
            speed: $this->speed + $delta->speed,
            endurance: $this->endurance + $delta->endurance,
            durability: $this->durability + $delta->durability,
            kiCapacity: $this->kiCapacity + $delta->kiCapacity,
            kiControl: $this->kiControl + $delta->kiControl,
            kiRecovery: $this->kiRecovery + $delta->kiRecovery,
            focus: $this->focus + $delta->focus,
            discipline: $this->discipline + $delta->discipline,
            adaptability: $this->adaptability + $delta->adaptability,
        );
    }
}

