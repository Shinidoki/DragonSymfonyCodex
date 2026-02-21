<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\World;
use App\Repository\SimulationDailyKpiRepository;
use App\Repository\WorldRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class WorldSimulationKpiController extends AbstractController
{
    #[Route('/api/worlds/{id}/simulation/kpis', name: 'api_world_simulation_kpis', methods: ['GET'])]
    public function index(int $id, Request $request, WorldRepository $worlds, SimulationDailyKpiRepository $kpis): JsonResponse
    {
        $world = $worlds->find($id);
        if (!$world instanceof World) {
            return $this->json(['error' => 'world_not_found'], 404);
        }

        $fromDay = $request->query->getInt('fromDay', 1);
        $toDay = $request->query->getInt('toDay', max(1, $world->getCurrentDay()));
        $limit = $request->query->getInt('limit', 2000);

        if ($fromDay < 0 || $toDay < $fromDay || $limit < 1) {
            return $this->json(['error' => 'invalid_range'], 400);
        }

        $rows = $kpis->findByWorldDayRange($world, $fromDay, $toDay, $limit);
        $snapshots = [];
        foreach ($rows as $row) {
            $snapshots[] = [
                'day' => $row->getDay(),
                'settlementsActive' => $row->getSettlementsActive(),
                'populationTotal' => $row->getPopulationTotal(),
                'unemployedCount' => $row->getUnemployedCount(),
                'unemploymentRate' => $row->getUnemploymentRate(),
                'migrationCommits' => $row->getMigrationCommits(),
                'tournamentAnnounced' => $row->getTournamentAnnounced(),
                'tournamentResolved' => $row->getTournamentResolved(),
                'tournamentCanceled' => $row->getTournamentCanceled(),
                'meanSettlementProsperity' => $row->getMeanSettlementProsperity(),
                'meanSettlementTreasury' => $row->getMeanSettlementTreasury(),
            ];
        }

        return $this->json([
            'worldId' => $world->getId(),
            'fromDay' => $fromDay,
            'toDay' => $toDay,
            'summary' => [
                'sampleSize' => count($snapshots),
            ],
            'snapshots' => $snapshots,
        ]);
    }
}
