<?php

namespace App\Game\Application\Local;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\CharacterGoal;
use App\Entity\LocalSession;
use App\Entity\NpcProfile;
use App\Entity\Settlement;
use App\Entity\World;
use App\Entity\WorldMapTile;
use App\Game\Application\Economy\EconomyCatalogProviderInterface;
use App\Game\Application\Goal\GoalCatalogProviderInterface;
use App\Game\Application\Settlement\ProjectCatalogProviderInterface;
use App\Game\Application\Settlement\SettlementProjectLifecycleService;
use App\Game\Application\Tournament\TournamentLifecycleService;
use App\Game\Domain\Economy\EconomyCatalog;
use App\Game\Domain\Map\TileCoord;
use App\Game\Domain\Simulation\SimulationClock;
use App\Game\Domain\Stats\Growth\TrainingIntensity;
use App\Game\Domain\Training\TrainingContext;
use App\Repository\CharacterEventRepository;
use App\Repository\CharacterGoalRepository;
use App\Repository\NpcProfileRepository;
use App\Repository\SettlementBuildingRepository;
use App\Repository\SettlementProjectRepository;
use App\Repository\SettlementRepository;
use App\Repository\WorldMapTileRepository;
use Doctrine\ORM\EntityManagerInterface;

final class StartLongActionHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SimulationClock        $clock,
        private readonly ?GoalCatalogProviderInterface $goalCatalogProvider = null,
        private readonly ?SettlementRepository            $settlements = null,
        private readonly ?EconomyCatalogProviderInterface $economyCatalogProvider = null,
        private readonly ?TournamentLifecycleService      $tournamentLifecycle = null,
        private readonly ?SettlementProjectRepository       $settlementProjects = null,
        private readonly ?SettlementBuildingRepository      $settlementBuildings = null,
        private readonly ?SettlementProjectLifecycleService $settlementProjectLifecycle = null,
        private readonly ?ProjectCatalogProviderInterface   $projectCatalogProvider = null,
    )
    {
    }

    public function start(int $sessionId, int $days, LongActionType $type, ?TrainingContext $trainingContext = null): LongActionResult
    {
        if ($days <= 0) {
            throw new \InvalidArgumentException('days must be positive.');
        }

        $session = $this->entityManager->find(LocalSession::class, $sessionId);
        if (!$session instanceof LocalSession) {
            throw new \RuntimeException(sprintf('Local session not found: %d', $sessionId));
        }
        if (!$session->isActive()) {
            throw new \RuntimeException('Local session must be active to start a long action.');
        }

        if ($type === LongActionType::Train && !$trainingContext instanceof TrainingContext) {
            throw new \InvalidArgumentException('Training requires a TrainingContext.');
        }

        $world = $this->entityManager->find(World::class, $session->getWorldId());
        if (!$world instanceof World) {
            throw new \RuntimeException(sprintf('World not found: %d', $session->getWorldId()));
        }

        $character = $this->entityManager->find(Character::class, $session->getCharacterId());
        if (!$character instanceof Character) {
            throw new \RuntimeException(sprintf('Character not found: %d', $session->getCharacterId()));
        }

        $session->suspend();
        $this->entityManager->flush();

        /** @var list<Character> $characters */
        $characters = $this->entityManager->getRepository(Character::class)->findBy(['world' => $world]);

        $profilesByCharacterId = [];
        /** @var NpcProfileRepository $npcProfiles */
        $npcProfiles = $this->entityManager->getRepository(NpcProfile::class);
        foreach ($npcProfiles->findByWorld($world) as $profile) {
            $id = $profile->getCharacter()->getId();
            if ($id !== null) {
                $profilesByCharacterId[(int)$id] = $profile;
            }
        }

        /** @var WorldMapTileRepository $tiles */
        $tiles = $this->entityManager->getRepository(WorldMapTile::class);
        /** @var list<WorldMapTile> $dojoTiles */
        $dojoTiles  = $tiles->findBy(['world' => $world, 'hasDojo' => true]);
        $dojoCoords = array_map(
            static fn(WorldMapTile $tile): TileCoord => new TileCoord($tile->getX(), $tile->getY()),
            $dojoTiles,
        );

        $settlementCoords   = [];
        $settlementEntities = [];
        $economyCatalog     = null;

        if ($this->settlements instanceof SettlementRepository && $this->economyCatalogProvider instanceof EconomyCatalogProviderInterface) {
            /** @var list<WorldMapTile> $settlementTiles */
            $settlementTiles = $tiles->findBy(['world' => $world, 'hasSettlement' => true]);
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

        if ($this->goalCatalogProvider instanceof GoalCatalogProviderInterface) {
            /** @var CharacterGoalRepository $goalRepo */
            $goalRepo = $this->entityManager->getRepository(CharacterGoal::class);
            foreach ($goalRepo->findByWorld($world) as $goal) {
                $id = $goal->getCharacter()->getId();
                if ($id !== null) {
                    $goalsByCharacterId[(int)$id] = $goal;
                }
            }

            /** @var CharacterEventRepository $eventRepo */
            $eventRepo = $this->entityManager->getRepository(CharacterEvent::class);
            $catalog = $this->goalCatalogProvider->get();

            $multiplier = null;
            if ($type === LongActionType::Train) {
                if ($trainingContext === TrainingContext::Dojo) {
                    [$settlementBuildingsByCoord, , $dojoTrainingMultipliersByCoord] = $this->settlementProjectContext(
                        dojoCoords: $dojoCoords,
                        settlements: $settlementEntities,
                    );
                    $coordKey   = sprintf('%d:%d', $character->getTileX(), $character->getTileY());
                    $multiplier = $dojoTrainingMultipliersByCoord[$coordKey] ?? $trainingContext->multiplier();
                } else {
                    $multiplier = $trainingContext->multiplier();
                }
            }

            for ($i = 0; $i < $days; $i++) {
                $events = $eventRepo->findByWorldUpToDay($world, $world->getCurrentDay());

                [$settlementBuildingsByCoord, $activeSettlementProjectsByCoord, $dojoTrainingMultipliersByCoord] = $this->settlementProjectContext(
                    dojoCoords: $dojoCoords,
                    settlements: $settlementEntities,
                );

                $emitted = $this->clock->advanceDaysForLongAction(
                    world: $world,
                    characters: $characters,
                    days: 1,
                    intensity: TrainingIntensity::Normal,
                    playerCharacterId: (int)$character->getId(),
                    trainingMultiplier: $multiplier,
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
            }

            $session->resume();
            $this->entityManager->flush();

            return new LongActionResult($world, $character, $session, $days);
        }

        $multiplier = null;
        if ($type === LongActionType::Train) {
            $multiplier = $trainingContext->multiplier();
        }

        $this->clock->advanceDaysForLongAction(
            world: $world,
            characters: $characters,
            days: $days,
            intensity: TrainingIntensity::Normal,
            playerCharacterId: (int)$character->getId(),
            trainingMultiplier: $multiplier,
            npcProfilesByCharacterId: $profilesByCharacterId,
            dojoTiles: $dojoCoords,
            goalsByCharacterId: $goalsByCharacterId,
            events: $events,
            goalCatalog: $catalog,
        );

        $this->entityManager->flush();

        $session->resume();
        $this->entityManager->flush();

        return new LongActionResult($world, $character, $session, $days);
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

    /**
     * @param list<TileCoord>  $dojoCoords
     * @param list<Settlement> $settlements
     *
     * @return array{
     *   0:array<string,array<string,int>>,
     *   1:array<string,array{building_code:string,target_level:int}>,
     *   2:array<string,float>
     * }
     */
    private function settlementProjectContext(array $dojoCoords, array $settlements): array
    {
        if (!$this->settlementProjects instanceof SettlementProjectRepository || !$this->settlementBuildings instanceof SettlementBuildingRepository) {
            return [[], [], []];
        }
        if ($settlements === []) {
            return [[], [], []];
        }

        $dojoIndex = [];
        foreach ($dojoCoords as $coord) {
            $dojoIndex[sprintf('%d:%d', $coord->x, $coord->y)] = true;
        }

        $buildingsByCoord = [];
        $projectsByCoord  = [];

        foreach ($settlements as $settlement) {
            $key = sprintf('%d:%d', $settlement->getX(), $settlement->getY());

            $dojo = $this->settlementBuildings->findOneBySettlementAndCode($settlement, 'dojo');
            if ($dojo !== null) {
                $buildingsByCoord[$key]['dojo'] = $dojo->getLevel();
            } elseif (isset($dojoIndex[$key])) {
                $buildingsByCoord[$key]['dojo'] = 1;
            }

            $active = $this->settlementProjects->findActiveForSettlement($settlement);
            if ($active !== null) {
                $projectsByCoord[$key] = [
                    'building_code' => $active->getBuildingCode(),
                    'target_level'  => $active->getTargetLevel(),
                ];
            }
        }

        $dojoTrainingMultipliersByCoord = [];
        if ($this->projectCatalogProvider instanceof ProjectCatalogProviderInterface) {
            $catalog = $this->projectCatalogProvider->get();
            foreach ($buildingsByCoord as $coordKey => $levels) {
                $level = $levels['dojo'] ?? 0;
                if (is_int($level) && $level > 0) {
                    $dojoTrainingMultipliersByCoord[$coordKey] = $catalog->dojoTrainingMultiplier($level);
                }
            }
        }

        return [$buildingsByCoord, $projectsByCoord, $dojoTrainingMultipliersByCoord];
    }

    private function hashInt(string $input): int
    {
        $hash = hash('sha256', $input);

        return (int)hexdec(substr($hash, 0, 8));
    }
}
