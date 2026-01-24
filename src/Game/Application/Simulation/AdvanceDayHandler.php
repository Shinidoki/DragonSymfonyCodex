<?php

namespace App\Game\Application\Simulation;

use App\Entity\Settlement;
use App\Entity\World;
use App\Entity\WorldMapTile;
use App\Game\Application\Economy\EconomyCatalogProviderInterface;
use App\Game\Application\Goal\GoalCatalogProviderInterface;
use App\Game\Domain\Map\TileCoord;
use App\Game\Domain\Simulation\SimulationClock;
use App\Game\Domain\Stats\Growth\TrainingIntensity;
use App\Repository\CharacterEventRepository;
use App\Repository\CharacterGoalRepository;
use App\Repository\CharacterRepository;
use App\Repository\NpcProfileRepository;
use App\Repository\SettlementRepository;
use App\Repository\WorldMapTileRepository;
use App\Repository\WorldRepository;
use Doctrine\ORM\EntityManagerInterface;

final class AdvanceDayHandler
{
    public function __construct(
        private readonly WorldRepository        $worldRepository,
        private readonly CharacterRepository    $characterRepository,
        private readonly NpcProfileRepository   $npcProfiles,
        private readonly WorldMapTileRepository $tiles,
        private readonly SimulationClock        $clock,
        private readonly EntityManagerInterface $entityManager,
        private readonly ?CharacterGoalRepository      $characterGoals = null,
        private readonly ?CharacterEventRepository     $characterEvents = null,
        private readonly ?GoalCatalogProviderInterface $goalCatalogProvider = null,
        private readonly ?SettlementRepository            $settlements = null,
        private readonly ?EconomyCatalogProviderInterface $economyCatalogProvider = null,
    )
    {
    }

    public function advance(int $worldId, int $days): AdvanceDayResult
    {
        if ($days < 0) {
            throw new \InvalidArgumentException('Days must be >= 0.');
        }

        $world = $this->worldRepository->find($worldId);
        if (!$world instanceof World) {
            throw new \RuntimeException(sprintf('World not found: %d', $worldId));
        }

        /** @var list<\App\Entity\Character> $characters */
        $characters = $this->characterRepository->findBy(['world' => $world]);

        $profilesByCharacterId = [];
        foreach ($this->npcProfiles->findByWorld($world) as $profile) {
            $id = $profile->getCharacter()->getId();
            if ($id !== null) {
                $profilesByCharacterId[(int)$id] = $profile;
            }
        }

        /** @var list<WorldMapTile> $dojoTiles */
        $dojoTiles  = $this->tiles->findBy(['world' => $world, 'hasDojo' => true]);
        $dojoCoords = array_map(
            static fn(WorldMapTile $tile): TileCoord => new TileCoord($tile->getX(), $tile->getY()),
            $dojoTiles,
        );

        $settlementCoords   = [];
        $settlementEntities = [];
        $economyCatalog     = null;

        if ($this->settlements instanceof SettlementRepository && $this->economyCatalogProvider instanceof EconomyCatalogProviderInterface) {
            /** @var list<WorldMapTile> $settlementTiles */
            $settlementTiles = $this->tiles->findBy(['world' => $world, 'hasSettlement' => true]);
            if ($settlementTiles !== []) {
                $settlementCoords = array_map(
                    static fn(WorldMapTile $tile): TileCoord => new TileCoord($tile->getX(), $tile->getY()),
                    $settlementTiles,
                );

                $economyCatalog     = $this->economyCatalogProvider->get();
                $settlementEntities = $this->ensureSettlements($world, $settlementTiles);
            }
        }

        $goalsByCharacterId = [];
        $events             = [];
        $catalog            = null;

        if (
            $this->characterGoals instanceof CharacterGoalRepository
            && $this->characterEvents instanceof CharacterEventRepository
            && $this->goalCatalogProvider instanceof GoalCatalogProviderInterface
        ) {
            foreach ($this->characterGoals->findByWorld($world) as $goal) {
                $id = $goal->getCharacter()->getId();
                if ($id !== null) {
                    $goalsByCharacterId[(int)$id] = $goal;
                }
            }

            $catalog = $this->goalCatalogProvider->get();

            for ($i = 0; $i < $days; $i++) {
                $events = $this->characterEvents->findByWorldUpToDay($world, $world->getCurrentDay());

                $emitted = $this->clock->advanceDays(
                    world: $world,
                    characters: $characters,
                    days: 1,
                    intensity: TrainingIntensity::Normal,
                    npcProfilesByCharacterId: $profilesByCharacterId,
                    dojoTiles: $dojoCoords,
                    settlementTiles: $settlementCoords,
                    goalsByCharacterId: $goalsByCharacterId,
                    events: $events,
                    goalCatalog: $catalog,
                    settlements: $settlementEntities,
                    economyCatalog: $economyCatalog,
                );

                foreach ($emitted as $event) {
                    $this->entityManager->persist($event);
                }

                $this->entityManager->flush();
            }

            return new AdvanceDayResult($world, $characters, $days);
        }

        $this->clock->advanceDays(
            world: $world,
            characters: $characters,
            days: $days,
            intensity: TrainingIntensity::Normal,
            npcProfilesByCharacterId: $profilesByCharacterId,
            dojoTiles: $dojoCoords,
            settlementTiles: $settlementCoords,
            goalsByCharacterId: $goalsByCharacterId,
            events: $events,
            goalCatalog: $catalog,
            settlements: $settlementEntities,
            economyCatalog: $economyCatalog,
        );
        $this->entityManager->flush();

        return new AdvanceDayResult($world, $characters, $days);
    }

    /**
     * @param list<WorldMapTile> $settlementTiles
     *
     * @return list<Settlement>
     */
    private function ensureSettlements(World $world, array $settlementTiles): array
    {
        if (!$this->settlements instanceof SettlementRepository) {
            return [];
        }

        /** @var list<Settlement> $existing */
        $existing = $this->settlements->findByWorld($world);

        $byCoord = [];
        foreach ($existing as $s) {
            $byCoord[sprintf('%d:%d', $s->getX(), $s->getY())] = $s;
        }

        foreach ($settlementTiles as $tile) {
            $key = sprintf('%d:%d', $tile->getX(), $tile->getY());
            if (isset($byCoord[$key])) {
                continue;
            }

            $settlement = new Settlement($world, $tile->getX(), $tile->getY());
            $settlement->setProsperity($this->initialProsperity($world->getSeed(), $tile->getX(), $tile->getY()));
            $this->entityManager->persist($settlement);

            $existing[]    = $settlement;
            $byCoord[$key] = $settlement;
        }

        return $existing;
    }

    private function initialProsperity(string $worldSeed, int $x, int $y): int
    {
        $n = $this->hashInt(sprintf('%s:settlement:prosperity:%d:%d', $worldSeed, $x, $y));

        return 25 + ($n % 51); // 25..75
    }

    private function hashInt(string $input): int
    {
        $hash = hash('sha256', $input);

        return (int)hexdec(substr($hash, 0, 8));
    }
}
