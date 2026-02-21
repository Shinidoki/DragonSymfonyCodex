<?php

declare(strict_types=1);

namespace App\Game\Domain\Simulation\Balancing;

/**
 * @phpstan-type MetricBounds array{min?:float,max?:float}
 * @phpstan-type ProfileDef array<string,MetricBounds>
 */
final readonly class SimulationBalancingCatalog
{
    /** @param array<string,ProfileDef> $profiles */
    public function __construct(private array $profiles)
    {
    }

    /** @return array<string,ProfileDef> */
    public function profiles(): array
    {
        return $this->profiles;
    }

    public function min(string $profile, string $metric): ?float
    {
        return $this->profiles[$profile][$metric]['min'] ?? null;
    }

    public function max(string $profile, string $metric): ?float
    {
        return $this->profiles[$profile][$metric]['max'] ?? null;
    }
}
