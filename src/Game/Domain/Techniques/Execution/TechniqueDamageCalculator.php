<?php

namespace App\Game\Domain\Techniques\Execution;

use App\Entity\Character;
use App\Entity\CharacterTechnique;
use App\Entity\TechniqueDefinition;
use App\Game\Domain\Stats\CoreAttributes;
use App\Game\Domain\Transformations\TransformationService;

final class TechniqueDamageCalculator
{
    public function __construct(
        private readonly TechniqueMath         $math = new TechniqueMath(),
        private readonly TransformationService $transformations = new TransformationService(),
    )
    {
    }

    public function damageFor(TechniqueDefinition $definition, CharacterTechnique $knowledge, Character $attacker, Character $defender): int
    {
        $config      = $definition->getConfig();
        $proficiency = $knowledge->getProficiency();

        $damageConfig = is_array($config['damage'] ?? null) ? $config['damage'] : [];
        $stat         = (string)($damageConfig['stat'] ?? 'kiControl');
        $statMult     = (float)($damageConfig['statMultiplier'] ?? 1.0);
        $base         = (int)($damageConfig['base'] ?? 0);
        $min          = (int)($damageConfig['min'] ?? 1);
        $min          = max(0, $min);

        $mitStat    = (string)($damageConfig['mitigationStat'] ?? 'durability');
        $mitDivisor = max(1, (int)($damageConfig['mitigationDivisor'] ?? 2));

        $atkEff = $this->transformations->effectiveAttributes($attacker->getCoreAttributes(), $attacker->getTransformationState());
        $defEff = $this->transformations->effectiveAttributes($defender->getCoreAttributes(), $defender->getTransformationState());

        $attackerValue = $this->attributeValue($atkEff, $stat);
        $defenderValue = $this->attributeValue($defEff, $mitStat);

        $raw = $base + (int)floor($attackerValue * $statMult);

        $mult    = 1.0;
        $effects = $config['proficiencyEffects'] ?? null;
        if (is_array($effects) && isset($effects['damageMultiplier']['at0'], $effects['damageMultiplier']['at100'])) {
            /** @var array{at0:float|int,at100:float|int} $curve */
            $curve = $effects['damageMultiplier'];
            $mult  = max(0.0, $this->math->curveAt($curve, $proficiency));
        }

        $raw = (int)floor($raw * $mult);

        $mitigation = intdiv(max(0, $defenderValue), $mitDivisor);
        $after      = $raw - $mitigation;

        return max($min, $after);
    }

    private function attributeValue(CoreAttributes $attributes, string $stat): int
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

