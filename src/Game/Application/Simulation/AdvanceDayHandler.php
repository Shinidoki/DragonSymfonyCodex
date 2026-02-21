<?php

namespace App\Game\Application\Simulation;

use App\Entity\Character;
use App\Entity\Settlement;
use App\Entity\World;
use App\Entity\WorldMapTile;
use App\Game\Application\Dojo\DojoLifecycleService;
use App\Game\Application\Economy\EconomyCatalogProviderInterface;
use App\Game\Application\Goal\GoalCatalogProviderInterface;
use App\Game\Application\Settlement\ProjectCatalogProviderInterface;
use App\Game\Application\Settlement\SettlementProjectLifecycleService;
use App\Game\Application\Tournament\TournamentInterestService;
use App\Game\Application\Tournament\TournamentLifecycleService;
use App\Game\Domain\Economy\EconomyCatalog;
use App\Game\Domain\Map\TileCoord;
use App\Game\Domain\Simulation\SimulationClock;
use App\Game\Domain\Stats\Growth\TrainingIntensity;
use App\Repository\CharacterEventRepository;
use App\Repository\CharacterGoalRepository;
use App\Repository\CharacterRepository;
use App\Repository\NpcProfileRepository;
use App\Repository\SettlementBuildingRepository;
use App\Repository\SettlementProjectRepository;
use App\Repository\SettlementRepository;
use App\Repository\WorldMapTileRepository;
use App\Repository\WorldRepository;
use Doctrine\ORM\EntityManagerInterface;

final class AdvanceDayHandler
{
    private const int MIN_POPULATION_TO_KEEP_SETTLEMENT   = 5;
    private const int MIN_WORLD_DAY_TO_ABANDON_SETTLEMENT = 3;

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
        private readonly ?TournamentLifecycleService $tournamentLifecycle = null,
        private readonly ?SettlementProjectRepository       $settlementProjects = null,
        private readonly ?SettlementBuildingRepository      $settlementBuildings = null,
        private readonly ?SettlementProjectLifecycleService $settlementProjectLifecycle = null,
        private readonly ?ProjectCatalogProviderInterface   $projectCatalogProvider = null,
        private readonly ?SettlementSimulationContextBuilder $settlementContextBuilder = null,
        private readonly ?DojoLifecycleService               $dojoLifecycle = null,
        private readonly ?TournamentInterestService          $tournamentInterestService = null,
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

                [
                    $settlementBuildingsByCoord,
                    $activeSettlementProjectsByCoord,
                    $dojoTrainingMultipliersByCoord,
                    $dojoMasterCharacterIdByCoord,
                    $dojoTrainingFeesByCoord,
                ] = $this->settlementContextBuilder?->build(dojoCoords: $dojoCoords, settlements: $settlementEntities) ?? [[], [], [], [], []];

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
                    settlementBuildingsByCoord: $settlementBuildingsByCoord,
                    activeSettlementProjectsByCoord: $activeSettlementProjectsByCoord,
                    dojoTrainingMultipliersByCoord: $dojoTrainingMultipliersByCoord,
                    dojoMasterCharacterIdByCoord: $dojoMasterCharacterIdByCoord,
                    dojoTrainingFeesByCoord: $dojoTrainingFeesByCoord,
                );

                foreach ($emitted as $event) {
                    $this->entityManager->persist($event);
                }

                $this->entityManager->flush();

                if (
                    $this->tournamentLifecycle instanceof TournamentLifecycleService
                    && $economyCatalog instanceof EconomyCatalog
                    && $settlementEntities !== []
                ) {
                    $tournamentEvents = $this->tournamentLifecycle->advanceDay(
                        world: $world,
                        worldDay: $world->getCurrentDay(),
                        characters: $characters,
                        goalsByCharacterId: $goalsByCharacterId,
                        emittedEvents: $emitted,
                        settlements: $settlementEntities,
                        economyCatalog: $economyCatalog,
                    );

                    foreach ($tournamentEvents as $event) {
                        $this->entityManager->persist($event);
                    }

                    $this->entityManager->flush();
                }

                if ($this->tournamentInterestService instanceof TournamentInterestService) {
                    $interestEvents = $this->tournamentInterestService->advanceDay(
                        world: $world,
                        worldDay: $world->getCurrentDay(),
                        characters: $characters,
                        goalsByCharacterId: $goalsByCharacterId,
                        npcProfilesByCharacterId: $profilesByCharacterId,
                    );

                    foreach ($interestEvents as $event) {
                        $this->entityManager->persist($event);
                    }

                    $this->entityManager->flush();

                    if (
                        $this->tournamentLifecycle instanceof TournamentLifecycleService
                        && $economyCatalog instanceof EconomyCatalog
                        && $settlementEntities !== []
                    ) {
                        $this->tournamentLifecycle->registerParticipantsForDay(
                            world: $world,
                            worldDay: $world->getCurrentDay(),
                            characters: $characters,
                            goalsByCharacterId: $goalsByCharacterId,
                        );
                        $this->entityManager->flush();
                    }
                }

                if (
                    $this->settlementProjectLifecycle instanceof SettlementProjectLifecycleService
                    && $economyCatalog instanceof EconomyCatalog
                    && $settlementEntities !== []
                ) {
                    $projectEvents = $this->settlementProjectLifecycle->advanceDay(
                        world: $world,
                        worldDay: $world->getCurrentDay(),
                        settlements: $settlementEntities,
                        emittedEvents: $emitted,
                        characters: $characters,
                        economyCatalog: $economyCatalog,
                    );

                    foreach ($projectEvents as $event) {
                        $this->entityManager->persist($event);
                    }

                    $this->entityManager->flush();
                }

                if (
                    $this->dojoLifecycle instanceof DojoLifecycleService
                    && $settlementEntities !== []
                ) {
                    $dojoEvents = $this->dojoLifecycle->advanceDay(
                        world: $world,
                        worldDay: $world->getCurrentDay(),
                        settlements: $settlementEntities,
                        characters: $characters,
                        emittedEvents: $emitted,
                    );

                    foreach ($dojoEvents as $event) {
                        $this->entityManager->persist($event);
                    }

                    $this->entityManager->flush();
                }

                if ($this->abandonUnderpopulatedSettlements($world, $characters)) {
                    $this->entityManager->flush();

                    if ($this->settlements instanceof SettlementRepository && $this->economyCatalogProvider instanceof EconomyCatalogProviderInterface) {
                        /** @var list<WorldMapTile> $settlementTiles */
                        $settlementTiles    = $this->tiles->findBy(['world' => $world, 'hasSettlement' => true]);
                        $settlementCoords   = array_map(
                            static fn(WorldMapTile $tile): TileCoord => new TileCoord($tile->getX(), $tile->getY()),
                            $settlementTiles,
                        );
                        $settlementEntities = $settlementTiles !== []
                            ? $this->ensureSettlements($world, $settlementTiles)
                            : [];
                    }
                }
            }

            return new AdvanceDayResult($world, $characters, $days);
        }

        $emitted = $this->clock->advanceDays(
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

        foreach ($emitted as $event) {
            $this->entityManager->persist($event);
        }
        $this->entityManager->flush();

        if ($this->abandonUnderpopulatedSettlements($world, $characters)) {
            $this->entityManager->flush();
        }

        return new AdvanceDayResult($world, $characters, $days);
    }

    /**
     * @param list<Character> $characters
     */
    private function abandonUnderpopulatedSettlements(World $world, array $characters): bool
    {
        if (!$this->settlements instanceof SettlementRepository) {
            return false;
        }

        if ($world->getCurrentDay() < self::MIN_WORLD_DAY_TO_ABANDON_SETTLEMENT) {
            return false;
        }

        /** @var list<WorldMapTile> $settlementTiles */
        $settlementTiles = $this->tiles->findBy(['world' => $world, 'hasSettlement' => true]);
        if ($settlementTiles === []) {
            return false;
        }

        $populationByCoord = [];
        $employedByCoord   = [];

        foreach ($characters as $character) {
            $id           = $character->getId();
            $characterKey = $id !== null ? 'id:' . (string)$id : 'obj:' . (string)spl_object_id($character);

            $hereKey                                    = sprintf('%d:%d', $character->getTileX(), $character->getTileY());
            $populationByCoord[$hereKey][$characterKey] = true;

            if ($character->isEmployed()) {
                $ex                                         = (int)$character->getEmploymentSettlementX();
                $ey                                         = (int)$character->getEmploymentSettlementY();
                $workKey                                    = sprintf('%d:%d', $ex, $ey);
                $populationByCoord[$workKey][$characterKey] = true;
                $employedByCoord[$workKey][]                = $character;
            }
        }

        $didAbandon = false;

        foreach ($settlementTiles as $tile) {
            $x = $tile->getX();
            $y = $tile->getY();

            // Keep an always-present "capital" anchor to avoid worlds collapsing to zero settlements.
            if ($x === 0 && $y === 0) {
                continue;
            }

            $key        = sprintf('%d:%d', $x, $y);
            $population = isset($populationByCoord[$key]) ? count($populationByCoord[$key]) : 0;
            if ($population >= self::MIN_POPULATION_TO_KEEP_SETTLEMENT) {
                continue;
            }

            $tile->setHasSettlement(false);
            $tile->setHasDojo(false);

            foreach ($employedByCoord[$key] ?? [] as $employedCharacter) {
                $employedCharacter->clearEmployment();
            }

            $didAbandon = true;
        }

        return $didAbandon;
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
