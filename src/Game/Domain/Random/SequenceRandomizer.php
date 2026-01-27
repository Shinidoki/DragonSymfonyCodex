<?php

namespace App\Game\Domain\Random;

/**
 * Deterministic RNG for tests.
 *
 * If the sequence is exhausted, it repeats the last value.
 */
final class SequenceRandomizer implements RandomizerInterface
{
    /** @var list<int> */
    private array $ints;

    private int $index = 0;

    /**
     * @param list<int> $ints Values used for nextInt(); they will be clamped into the requested range.
     */
    public function __construct(array $ints)
    {
        if ($ints === []) {
            throw new \InvalidArgumentException('ints must not be empty.');
        }

        $this->ints = array_values($ints);
    }

    public function nextInt(int $min, int $max): int
    {
        if ($min > $max) {
            throw new \InvalidArgumentException('min must be <= max.');
        }

        $raw = $this->ints[min($this->index, count($this->ints) - 1)];
        $this->index++;

        if ($raw < $min) {
            return $min;
        }
        if ($raw > $max) {
            return $max;
        }

        return $raw;
    }

    public function chance(float $p): bool
    {
        $p = max(0.0, min(1.0, $p));
        if ($p <= 0.0) {
            return false;
        }
        if ($p >= 1.0) {
            return true;
        }

        // Use nextInt deterministically.
        $n = $this->nextInt(1, 1_000_000);
        return $n <= (int)floor($p * 1_000_000);
    }
}
