<?php

declare(strict_types=1);

namespace App\Game\Application\Simulation;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\Settlement;
use App\Entity\SimulationDailyKpi;
use App\Entity\World;
use Doctrine\ORM\EntityManagerInterface;

final class SimulationDailyKpiRecorder
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @param list<Character> $characters
     * @param list<Settlement> $settlements
     * @param list<CharacterEvent> $emittedEvents
     */
    public function recordDay(World $world, int $day, array $characters, array $settlements, array $emittedEvents): SimulationDailyKpi
    {
        $populationTotal = count($characters);
        $unemployedCount = count(array_filter($characters, static fn (Character $c): bool => !$c->isEmployed()));
        $unemploymentRate = $populationTotal > 0 ? ($unemployedCount / $populationTotal) : 0.0;

        $settlementsActive = count($settlements);
        $totalProsperity = array_sum(array_map(static fn (Settlement $s): int => $s->getProsperity(), $settlements));
        $totalTreasury = array_sum(array_map(static fn (Settlement $s): int => $s->getTreasury(), $settlements));
        $meanSettlementProsperity = $settlementsActive > 0 ? ($totalProsperity / $settlementsActive) : 0.0;
        $meanSettlementTreasury = $settlementsActive > 0 ? ($totalTreasury / $settlementsActive) : 0.0;

        $counts = [
            'settlement_migration_committed' => 0,
            'tournament_announced' => 0,
            'tournament_resolved' => 0,
            'tournament_canceled' => 0,
        ];
        foreach ($emittedEvents as $event) {
            $type = $event->getType();
            if (array_key_exists($type, $counts)) {
                $counts[$type]++;
            }
        }

        $kpi = new SimulationDailyKpi(
            world: $world,
            day: $day,
            settlementsActive: $settlementsActive,
            populationTotal: $populationTotal,
            unemployedCount: $unemployedCount,
            unemploymentRate: $unemploymentRate,
            migrationCommits: $counts['settlement_migration_committed'],
            tournamentAnnounced: $counts['tournament_announced'],
            tournamentResolved: $counts['tournament_resolved'],
            tournamentCanceled: $counts['tournament_canceled'],
            meanSettlementProsperity: $meanSettlementProsperity,
            meanSettlementTreasury: $meanSettlementTreasury,
        );

        $this->entityManager->persist($kpi);

        return $kpi;
    }
}
