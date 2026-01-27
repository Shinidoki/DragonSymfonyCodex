<?php

namespace App\Game\Domain\Random;

interface RandomizerInterface
{
    public function nextInt(int $min, int $max): int;

    /**
     * @param float $p probability in range 0..1
     */
    public function chance(float $p): bool;
}
