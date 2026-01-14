<?php

namespace App\Game\Domain\Techniques\Execution;

use App\Game\Domain\Techniques\TechniqueType;
use App\Game\Domain\Transformations\TransformationService;

class BlastExecutor implements TechniqueExecutor
{
    public function __construct(
        private readonly TechniqueMath $math = new TechniqueMath(),
        private readonly DeterministicRoller $roller = new DeterministicRoller(),
        private readonly TransformationService $transformations = new TransformationService(),
    )
    {
    }

    public function supports(TechniqueType $type): bool
    {
        return $type === TechniqueType::Blast;
    }

    public function execute(TechniqueContext $context): TechniqueExecution
    {
        $config      = $context->definition->getConfig();
        $proficiency = $context->knowledge->getProficiency();

        $baseCost = (int)($config['kiCost'] ?? 1);
        $baseCost = max(0, $baseCost);

        $proficiencyEffects = is_array($config['proficiencyEffects'] ?? null) ? $config['proficiencyEffects'] : [];
        $kiCostMultiplier   = 1.0;
        if (is_array($proficiencyEffects) && isset($proficiencyEffects['kiCostMultiplier']['at0'], $proficiencyEffects['kiCostMultiplier']['at100'])) {
            /** @var array{at0:float|int,at100:float|int} $curve */
            $curve           = $proficiencyEffects['kiCostMultiplier'];
            $kiCostMultiplier = $this->math->curveAt($curve, $proficiency);
        }

        $effectiveCost = (int)ceil($baseCost * max(0.0, $kiCostMultiplier));

        $successChance = 1.0;
        if (isset($config['successChance']['at0'], $config['successChance']['at100'])) {
            /** @var array{at0:float|int,at100:float|int} $curve */
            $curve         = $config['successChance'];
            $successChance = $this->math->clamp01($this->math->curveAt($curve, $proficiency));
        }

        $seed = sprintf(
            'tech:%d:%d:%d:%s',
            $context->sessionId,
            $context->tick,
            (int)$context->attackerActor->getId(),
            $context->definition->getCode(),
        );

        $success = $this->roller->roll($seed, $successChance);

        if (!$success) {
            $mult = (float)($config['failureKiCostMultiplier'] ?? 0.5);
            $mult = max(0.0, min(1.0, $mult));
            $spent = (int)ceil($effectiveCost * $mult);

            return new TechniqueExecution(
                success: false,
                kiSpent: $spent,
                damage: 0,
                defenderDefeated: false,
                message: sprintf('%s fails to use %s.', $context->attackerCharacter->getName(), $context->definition->getName()),
            );
        }

        $damage = $this->computeDamage($context);

        return new TechniqueExecution(
            success: true,
            kiSpent: $effectiveCost,
            damage: $damage,
            defenderDefeated: false,
            message: sprintf(
                '%s uses %s on %s for %d damage.',
                $context->attackerCharacter->getName(),
                $context->definition->getName(),
                $context->defenderCharacter->getName(),
                $damage,
            ),
        );
    }

    private function computeDamage(TechniqueContext $context): int
    {
        $config      = $context->definition->getConfig();
        $proficiency = $context->knowledge->getProficiency();

        $damageConfig = is_array($config['damage'] ?? null) ? $config['damage'] : [];
        $stat         = (string)($damageConfig['stat'] ?? 'kiControl');
        $statMult     = (float)($damageConfig['statMultiplier'] ?? 1.0);
        $base         = (int)($damageConfig['base'] ?? 0);
        $min          = (int)($damageConfig['min'] ?? 1);

        $mitStat      = (string)($damageConfig['mitigationStat'] ?? 'durability');
        $mitDivisor   = max(1, (int)($damageConfig['mitigationDivisor'] ?? 2));

        $atkEff = $this->transformations->effectiveAttributes($context->attackerCharacter->getCoreAttributes(), $context->attackerCharacter->getTransformationState());
        $defEff = $this->transformations->effectiveAttributes($context->defenderCharacter->getCoreAttributes(), $context->defenderCharacter->getTransformationState());

        $attackerValue = $this->attributeValue($atkEff, $stat);
        $defenderValue = $this->attributeValue($defEff, $mitStat);

        $raw = $base + (int)floor($attackerValue * $statMult);

        $proficiencyEffects = is_array($config['proficiencyEffects'] ?? null) ? $config['proficiencyEffects'] : [];
        $damageMultiplier   = 1.0;
        if (is_array($proficiencyEffects) && isset($proficiencyEffects['damageMultiplier']['at0'], $proficiencyEffects['damageMultiplier']['at100'])) {
            /** @var array{at0:float|int,at100:float|int} $curve */
            $curve            = $proficiencyEffects['damageMultiplier'];
            $damageMultiplier = $this->math->curveAt($curve, $proficiency);
        }

        $raw = (int)floor($raw * max(0.0, $damageMultiplier));

        $mitigation = intdiv(max(0, $defenderValue), $mitDivisor);
        $after = $raw - $mitigation;

        return max($min, $after);
    }

    private function attributeValue(\App\Game\Domain\Stats\CoreAttributes $attributes, string $stat): int
    {
        return match (strtolower($stat)) {
            'strength' => $attributes->strength,
            'speed' => $attributes->speed,
            'endurance' => $attributes->endurance,
            'durability' => $attributes->durability,
            'kicapacity', 'ki_capacity' => $attributes->kiCapacity,
            'kicontrol', 'ki_control' => $attributes->kiControl,
            'kirecovery', 'ki_recovery' => $attributes->kiRecovery,
            'focus' => $attributes->focus,
            'discipline' => $attributes->discipline,
            'adaptability' => $attributes->adaptability,
            default => $attributes->kiControl,
        };
    }
}
