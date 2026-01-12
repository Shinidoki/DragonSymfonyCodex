<?php

namespace App\Game\Domain\Transformations;

final readonly class TransformationState
{
    public function __construct(
        public ?Transformation $active = null,
        public int             $activeTicks = 0,
        public int             $exhaustionDaysRemaining = 0,
    )
    {
        if ($activeTicks < 0) {
            throw new \InvalidArgumentException('activeTicks must be >= 0.');
        }
        if ($exhaustionDaysRemaining < 0) {
            throw new \InvalidArgumentException('exhaustionDaysRemaining must be >= 0.');
        }
    }

    public static function none(): self
    {
        return new self();
    }

    public function withActive(Transformation $transformation): self
    {
        return new self(active: $transformation, activeTicks: 0, exhaustionDaysRemaining: $this->exhaustionDaysRemaining);
    }

    public function tick(): self
    {
        if ($this->active === null) {
            return $this;
        }

        return new self(active: $this->active, activeTicks: $this->activeTicks + 1, exhaustionDaysRemaining: $this->exhaustionDaysRemaining);
    }

    public function deactivateWithExhaustion(int $exhaustionDays): self
    {
        return new self(active: null, activeTicks: 0, exhaustionDaysRemaining: $exhaustionDays);
    }

    public function recoverOneDay(): self
    {
        if ($this->exhaustionDaysRemaining <= 0) {
            return $this;
        }

        return new self(active: null, activeTicks: 0, exhaustionDaysRemaining: $this->exhaustionDaysRemaining - 1);
    }
}

