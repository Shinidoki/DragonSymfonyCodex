<?php

namespace App\Game\Domain\Time;

final readonly class Day
{
    public function __construct(public int $value)
    {
        if ($value < 0) {
            throw new \InvalidArgumentException('Day value must be >= 0.');
        }
    }

    public function plus(int $delta): self
    {
        return new self($this->value + $delta);
    }
}

