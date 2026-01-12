<?php

namespace App\Game\Domain\LocalTurns;

final class TurnMeter
{
    public function __construct(private int $value = 0)
    {
        if ($value < 0) {
            throw new \InvalidArgumentException('Turn meter must be >= 0.');
        }
    }

    public function value(): int
    {
        return $this->value;
    }

    public function add(int $amount): self
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Amount must be >= 0.');
        }

        return new self($this->value + $amount);
    }

    public function subtract(int $amount): self
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Amount must be >= 0.');
        }
        if ($amount > $this->value) {
            throw new \InvalidArgumentException('Amount must be <= current meter.');
        }

        return new self($this->value - $amount);
    }
}