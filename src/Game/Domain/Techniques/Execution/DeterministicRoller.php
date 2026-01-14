<?php

namespace App\Game\Domain\Techniques\Execution;

final class DeterministicRoller
{
    public function roll(string $seed, float $p): bool
    {
        $p = max(0.0, min(1.0, $p));
        if ($p <= 0.0) {
            return false;
        }
        if ($p >= 1.0) {
            return true;
        }

        $hash = hash('sha256', $seed);
        $hi   = substr($hash, 0, 8);
        $n    = hexdec($hi);
        $u    = $n / 0xFFFFFFFF;

        return $u < $p;
    }
}

