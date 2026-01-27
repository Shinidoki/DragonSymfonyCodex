<?php

namespace App\Game\Domain\Settlement;

/**
 * @phpstan-type DojoLevelDef array{
 *   materials_cost:int,
 *   base_required_work_units:int,
 *   target_duration_days:int,
 *   diversion_fraction:float,
 *   training_multiplier:float
 * }
 * @phpstan-type DojoRulesDef array{
 *   challenge_cooldown_days:int,
 *   training_fee_base:int,
 *   training_fee_per_level:int
 * }
 */
final readonly class ProjectCatalog
{
    /**
     * @param array<int,DojoLevelDef> $dojoLevels
     * @param DojoRulesDef $dojoRules
     */
    public function __construct(private array $dojoLevels, private array $dojoRules)
    {
        if ($dojoLevels === []) {
            throw new \InvalidArgumentException('dojoLevels must not be empty.');
        }
    }

    /**
     * @return array<int,DojoLevelDef>
     */
    public function dojoLevelDefs(): array
    {
        return $this->dojoLevels;
    }

    public function dojoTrainingMultiplier(int $level): float
    {
        if ($level <= 0) {
            return 1.0;
        }

        $def = $this->dojoLevels[$level] ?? null;
        if (!is_array($def)) {
            throw new \InvalidArgumentException(sprintf('Unknown dojo level: %d', $level));
        }

        return (float)$def['training_multiplier'];
    }

    public function dojoTrainingFee(int $level): int
    {
        if ($level <= 0) {
            $level = 1;
        }

        $base = (int)$this->dojoRules['training_fee_base'];
        $per  = (int)$this->dojoRules['training_fee_per_level'];

        $fee = $base + ($per * ($level - 1));
        if ($fee < 0) {
            $fee = 0;
        }

        return $fee;
    }

    public function dojoChallengeCooldownDays(): int
    {
        return (int)$this->dojoRules['challenge_cooldown_days'];
    }

    public function dojoNextLevel(int $currentLevel): ?int
    {
        if ($currentLevel < 0) {
            throw new \InvalidArgumentException('currentLevel must be >= 0.');
        }

        $next = $currentLevel + 1;

        return array_key_exists($next, $this->dojoLevels) ? $next : null;
    }
}
