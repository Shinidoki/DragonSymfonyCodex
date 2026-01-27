<?php

namespace App\Game\Domain\Random;

final class PhpRandomizer implements RandomizerInterface
{
    public function nextInt(int $min, int $max): int
    {
        return random_int($min, $max);
    }

    public function chance(float $p): bool
    {
        if ($p <= 0.0) {
            return false;
        }
        if ($p >= 1.0) {
            return true;
        }

        // Compare integers to avoid float RNG issues.
        $n = $this->nextInt(1, 1_000_000);
        return $n <= (int)floor($p * 1_000_000);
    }
}
