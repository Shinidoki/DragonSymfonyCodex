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
use App\Game\Application\Tournament\TournamentLifecycleService;
use App\Game\Domain\Economy\EconomyCatalog;
use App\Game\Domain\Map\TileCoord;
use App\Game\Domain\Simulation\SimulationClock;
use App\Game\Domain\Stats\Growth\TrainingIntensity;
use App\Game\Domain\Training\TrainingContext;
use App\Repository\CharacterEventRepository;
use App\Repository\CharacterGoalRepository;
use App\Repository\NpcProfileRepository;
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
                $multiplier = $trainingContext->multiplier();
            }

            for ($i = 0; $i < $days; $i++) {
                $events = $eventRepo->findByWorldUpToDay($world, $world->getCurrentDay());

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

    private function hashInt(string $input): int
    {
        $hash = hash('sha256', $input);

        return (int)hexdec(substr($hash, 0, 8));
    }
}
