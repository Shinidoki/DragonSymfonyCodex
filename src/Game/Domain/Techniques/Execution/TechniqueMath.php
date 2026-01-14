<?php

namespace App\Game\Domain\Techniques\Execution;

final class TechniqueMath
{
    public function lerp(float $a, float $b, float $t): float
    {
        return $a + (($b - $a) * $t);
    }

    public function proficiencyT(int $proficiency): float
    {
        if ($proficiency < 0 || $proficiency > 100) {
            throw new \InvalidArgumentException('proficiency must be in range 0..100.');
        }

        return $proficiency / 100.0;
    }

    /**
        * @param array{at0:float|int,at100:float|int} $curve
     */
    public function curveAt(array $curve, int $proficiency): float
    {
        $t = $this->proficiencyT($proficiency);
        return $this->lerp((float)$curve['at0'], (float)$curve['at100'], $t);
    }

    public function clamp01(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }
}

