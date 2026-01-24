<?php

namespace App\Game\Domain\Techniques\Execution;

use App\Entity\CharacterTechnique;
use App\Entity\TechniqueDefinition;

final class TechniqueUseCalculator
{
    public function __construct(
        private readonly TechniqueMath       $math = new TechniqueMath(),
        private readonly DeterministicRoller $roller = new DeterministicRoller(),
    )
    {
    }

    public function effectiveKiCost(TechniqueDefinition $definition, CharacterTechnique $knowledge): int
    {
        $config      = $definition->getConfig();
        $proficiency = $knowledge->getProficiency();

        $baseCost = (int)($config['kiCost'] ?? 0);
        $baseCost = max(0, $baseCost);

        $mult    = 1.0;
        $effects = $config['proficiencyEffects'] ?? null;
        if (is_array($effects) && isset($effects['kiCostMultiplier']['at0'], $effects['kiCostMultiplier']['at100'])) {
            /** @var array{at0:float|int,at100:float|int} $curve */
            $curve = $effects['kiCostMultiplier'];
            $mult  = max(0.0, $this->math->curveAt($curve, $proficiency));
        }

        return (int)ceil($baseCost * $mult);
    }

    public function successChance(TechniqueDefinition $definition, CharacterTechnique $knowledge): float
    {
        $config      = $definition->getConfig();
        $proficiency = $knowledge->getProficiency();

        $chance = 1.0;
        if (isset($config['successChance']['at0'], $config['successChance']['at100'])) {
            /** @var array{at0:float|int,at100:float|int} $curve */
            $curve  = $config['successChance'];
            $chance = $this->math->clamp01($this->math->curveAt($curve, $proficiency));
        }

        return $chance;
    }

    public function rollSuccess(TechniqueDefinition $definition, CharacterTechnique $knowledge, int $sessionId, int $tick, int $attackerActorId): bool
    {
        $p    = $this->successChance($definition, $knowledge);
        $seed = sprintf('tech:%d:%d:%d:%s', $sessionId, $tick, $attackerActorId, $definition->getCode());
        return $this->roller->roll($seed, $p);
    }

    public function failureKiCostMultiplier(TechniqueDefinition $definition): float
    {
        $config = $definition->getConfig();
        $mult   = (float)($config['failureKiCostMultiplier'] ?? 0.5);
        return max(0.0, min(1.0, $mult));
    }
}

