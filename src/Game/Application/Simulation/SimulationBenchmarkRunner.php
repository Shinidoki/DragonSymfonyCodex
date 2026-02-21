<?php

declare(strict_types=1);

namespace App\Game\Application\Simulation;

use App\Entity\SimulationDailyKpi;
use App\Entity\World;
use App\Repository\SimulationDailyKpiRepository;

final class SimulationBenchmarkRunner implements SimulationBenchmarkRunnerInterface
{
    public function __construct(
        private readonly SimulationDailyKpiRepository $kpiRepository,
        private readonly SimulationBalancingCatalogProviderInterface $catalogProvider,
    ) {
    }

    /**
     * @return array{passed:bool,profile:string,sample_size:int,violations:list<array{metric:string,kind:string,observed:float,min?:float,max?:float}>}
     */
    public function run(World $world, int $days, string $profile): array
    {
        if ($days < 1) {
            throw new \InvalidArgumentException('days must be >= 1.');
        }

        $toDay = $world->getCurrentDay();
        $fromDay = max(0, $toDay - $days + 1);

        $rows = $this->kpiRepository->findByWorldDayRange($world, $fromDay, $toDay, $days);
        $catalog = $this->catalogProvider->get();
        $profiles = $catalog->profiles();
        $thresholds = $profiles[$profile] ?? null;
        if (!is_array($thresholds)) {
            throw new \InvalidArgumentException(sprintf('Unknown balancing profile: %s', $profile));
        }

        $violations = [];
        foreach ($thresholds as $metric => $bounds) {
            $values = array_map(static fn (SimulationDailyKpi $kpi): float => self::metricValue($kpi, $metric), $rows);
            if ($values === []) {
                continue;
            }
            $observedMin = min($values);
            $observedMax = max($values);

            if (isset($bounds['min']) && $observedMin < (float) $bounds['min']) {
                $violations[] = ['metric' => $metric, 'kind' => 'min', 'observed' => $observedMin, 'min' => (float) $bounds['min']];
            }
            if (isset($bounds['max']) && $observedMax > (float) $bounds['max']) {
                $violations[] = ['metric' => $metric, 'kind' => 'max', 'observed' => $observedMax, 'max' => (float) $bounds['max']];
            }
        }

        return [
            'passed' => $violations === [],
            'profile' => $profile,
            'sample_size' => count($rows),
            'violations' => $violations,
        ];
    }

    private static function metricValue(SimulationDailyKpi $kpi, string $metric): float
    {
        return match ($metric) {
            'settlements_active' => (float) $kpi->getSettlementsActive(),
            'population_total' => (float) $kpi->getPopulationTotal(),
            'unemployed_count' => (float) $kpi->getUnemployedCount(),
            'unemployment_rate' => $kpi->getUnemploymentRate(),
            'migration_commits' => (float) $kpi->getMigrationCommits(),
            'tournament_announced' => (float) $kpi->getTournamentAnnounced(),
            'tournament_resolved' => (float) $kpi->getTournamentResolved(),
            'tournament_canceled' => (float) $kpi->getTournamentCanceled(),
            'mean_settlement_prosperity' => $kpi->getMeanSettlementProsperity(),
            'mean_settlement_treasury' => $kpi->getMeanSettlementTreasury(),
            default => throw new \InvalidArgumentException(sprintf('Unknown KPI metric: %s', $metric)),
        };
    }
}
