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
 */
final readonly class ProjectCatalog
{
    /**
     * @param array<int,DojoLevelDef> $dojoLevels
     */
    public function __construct(private array $dojoLevels)
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

    public function dojoNextLevel(int $currentLevel): ?int
    {
        if ($currentLevel < 0) {
            throw new \InvalidArgumentException('currentLevel must be >= 0.');
        }

        $next = $currentLevel + 1;

        return array_key_exists($next, $this->dojoLevels) ? $next : null;
    }
}
