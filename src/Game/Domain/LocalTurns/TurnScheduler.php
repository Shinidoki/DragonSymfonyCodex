<?php

namespace App\Game\Domain\LocalTurns;

/**
 * Deterministic, integer-only turn scheduling.
 *
 * Model:
 * - Each actor has an initiative "meter" (>= 0).
 * - "Time" advances just enough for at least one actor to reach threshold.
 * - The next actor to act is chosen deterministically; their meter is reduced by threshold.
 *
 * This keeps meters non-negative (important for persistence) while still making higher speed
 * actors act more frequently.
 */
final class TurnScheduler
{
    public function __construct(private readonly int $threshold = 100)
    {
        if ($threshold <= 0) {
            throw new \InvalidArgumentException('threshold must be > 0.');
        }
    }

    /**
     * @param array<int,array{id:int,speed:int,meter:int}> $actors
     */
    public function pickNextActorId(array &$actors): int
    {
        if ($actors === []) {
            throw new \InvalidArgumentException('actors must not be empty.');
        }

        $hasReadyActor = false;
        foreach ($actors as $actor) {
            if (!isset($actor['id'], $actor['speed'], $actor['meter'])) {
                throw new \InvalidArgumentException('each actor must have id, speed, and meter.');
            }
            if (!is_int($actor['id']) || $actor['id'] <= 0) {
                throw new \InvalidArgumentException('actor id must be a positive integer.');
            }
            if (!is_int($actor['speed']) || $actor['speed'] <= 0) {
                throw new \InvalidArgumentException('actor speed must be a positive integer.');
            }
            if (!is_int($actor['meter']) || $actor['meter'] < 0) {
                throw new \InvalidArgumentException('actor meter must be an integer >= 0.');
            }

            if ($actor['meter'] >= $this->threshold) {
                $hasReadyActor = true;
            }
        }

        if (!$hasReadyActor) {
            $steps = null;
            foreach ($actors as $actor) {
                $missing = $this->threshold - $actor['meter'];
                $needed  = $this->ceilDiv($missing, $actor['speed']);
                $steps   = $steps === null ? $needed : min($steps, $needed);
            }
            $steps ??= 0;

            foreach ($actors as $i => $actor) {
                $actors[$i]['meter'] = $actor['meter'] + ($actor['speed'] * $steps);
            }
        }

        $bestIndex = null;
        $bestMeter = null;
        $bestId    = null;

        foreach ($actors as $i => $actor) {
            if ($actor['meter'] < $this->threshold) {
                continue;
            }

            if ($bestIndex === null) {
                $bestIndex = $i;
                $bestMeter = $actor['meter'];
                $bestId    = $actor['id'];
                continue;
            }

            if ($actor['meter'] > $bestMeter) {
                $bestIndex = $i;
                $bestMeter = $actor['meter'];
                $bestId    = $actor['id'];
                continue;
            }

            if ($actor['meter'] === $bestMeter && $actor['id'] < $bestId) {
                $bestIndex = $i;
                $bestId    = $actor['id'];
            }
        }

        if ($bestIndex === null || $bestId === null) {
            throw new \LogicException('No actor reached the threshold after advancing time.');
        }

        $actors[$bestIndex]['meter'] -= $this->threshold;

        return $bestId;
    }

    private function ceilDiv(int $numerator, int $denominator): int
    {
        if ($numerator <= 0) {
            return 0;
        }
        if ($denominator <= 0) {
            throw new \InvalidArgumentException('denominator must be > 0.');
        }

        return intdiv($numerator + $denominator - 1, $denominator);
    }
}