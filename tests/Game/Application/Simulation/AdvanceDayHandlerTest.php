<?php

namespace App\Tests\Game\Application\Simulation;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\CharacterGoal;
use App\Entity\World;
use App\Game\Application\Goal\GoalCatalogProviderInterface;
use App\Game\Application\Simulation\AdvanceDayHandler;
use App\Game\Application\Tournament\TournamentInterestService;
use App\Game\Domain\Goal\GoalCatalog;
use App\Game\Domain\Goal\GoalPlanner;
use App\Game\Domain\Goal\Handlers\ParticipateTournamentGoalHandler;
use App\Game\Domain\Race;
use App\Game\Domain\Simulation\SimulationClock;
use App\Game\Domain\Stats\Growth\TrainingGrowthService;
use App\Repository\CharacterEventRepository;
use App\Repository\CharacterGoalRepository;
use App\Repository\CharacterRepository;
use App\Repository\NpcProfileRepository;
use App\Repository\WorldMapTileRepository;
use App\Repository\WorldRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class AdvanceDayHandlerTest extends TestCase
{
    public function testAdvanceIncrementsWorldDayAndTrainsCharacters(): void
    {
        $world     = new World('seed-1');
        $character = new Character($world, 'Gohan', Race::Human);

        $worldRepository = $this->createMock(WorldRepository::class);
        $worldRepository->expects(self::once())
            ->method('find')
            ->with(1)
            ->willReturn($world);

        $characterRepository = $this->createMock(CharacterRepository::class);
        $characterRepository->expects(self::once())
            ->method('findBy')
            ->with(['world' => $world])
            ->willReturn([$character]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $clock = new SimulationClock(new TrainingGrowthService());

        $npcProfiles = $this->createMock(NpcProfileRepository::class);
        $npcProfiles->expects(self::once())
            ->method('findByWorld')
            ->with($world)
            ->willReturn([]);

        $tiles = $this->createMock(WorldMapTileRepository::class);
        $tiles->expects(self::once())
            ->method('findBy')
            ->with(['world' => $world, 'hasDojo' => true])
            ->willReturn([]);

        $handler = new AdvanceDayHandler($worldRepository, $characterRepository, $npcProfiles, $tiles, $clock, $entityManager);

        $beforeStrength = $character->getStrength();

        $result = $handler->advance(1, 1);

        self::assertSame(1, $result->daysAdvanced);
        self::assertSame(1, $result->world->getCurrentDay());
        self::assertGreaterThan($beforeStrength, $character->getStrength());
    }

    public function testAdvanceMovesTravelingCharacterInsteadOfTraining(): void
    {
        $world     = new World('seed-1');
        $character = new Character($world, 'Bulma', Race::Human);
        $character->setTravelTarget(1, 0);

        $worldRepository = $this->createMock(WorldRepository::class);
        $worldRepository->expects(self::once())
            ->method('find')
            ->with(1)
            ->willReturn($world);

        $characterRepository = $this->createMock(CharacterRepository::class);
        $characterRepository->expects(self::once())
            ->method('findBy')
            ->with(['world' => $world])
            ->willReturn([$character]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $clock   = new SimulationClock(new TrainingGrowthService());

        $npcProfiles = $this->createMock(NpcProfileRepository::class);
        $npcProfiles->expects(self::once())
            ->method('findByWorld')
            ->with($world)
            ->willReturn([]);

        $tiles = $this->createMock(WorldMapTileRepository::class);
        $tiles->expects(self::once())
            ->method('findBy')
            ->with(['world' => $world, 'hasDojo' => true])
            ->willReturn([]);

        $handler = new AdvanceDayHandler($worldRepository, $characterRepository, $npcProfiles, $tiles, $clock, $entityManager);

        $beforeStrength = $character->getStrength();

        $handler->advance(1, 1);

        self::assertSame(1, $character->getTileX());
        self::assertSame(0, $character->getTileY());
        self::assertFalse($character->hasTravelTarget());
        self::assertSame($beforeStrength, $character->getStrength());
    }

    public function testAdvanceUsesCurrentGoalHandlersForDailyPlans(): void
    {
        $world     = new World('seed-1');
        $character = new Character($world, 'Bulma', Race::Human);
        $character->setTilePosition(0, 0);
        $this->setEntityId($character, 1);

        $goals = new CharacterGoal($character);
        $goals->setLifeGoalCode('fighter.become_strongest');
        $goals->setCurrentGoalCode('goal.participate_tournament');
        $goals->setCurrentGoalData(['center_x' => 1, 'center_y' => 0, 'resolve_day' => 3]);
        $goals->setCurrentGoalComplete(false);

        $worldRepository = $this->createMock(WorldRepository::class);
        $worldRepository->expects(self::once())
            ->method('find')
            ->with(1)
            ->willReturn($world);

        $characterRepository = $this->createMock(CharacterRepository::class);
        $characterRepository->expects(self::once())
            ->method('findBy')
            ->with(['world' => $world])
            ->willReturn([$character]);

        $npcProfiles = $this->createMock(NpcProfileRepository::class);
        $npcProfiles->expects(self::once())
            ->method('findByWorld')
            ->with($world)
            ->willReturn([]);

        $tiles = $this->createMock(WorldMapTileRepository::class);
        $tiles->expects(self::once())
            ->method('findBy')
            ->with(['world' => $world, 'hasDojo' => true])
            ->willReturn([]);

        $characterGoals = $this->createMock(CharacterGoalRepository::class);
        $characterGoals->expects(self::once())
            ->method('findByWorld')
            ->with($world)
            ->willReturn([$goals]);

        $characterEvents = $this->createMock(CharacterEventRepository::class);
        $characterEvents->expects(self::once())
            ->method('findByWorldUpToDay')
            ->with($world, 0)
            ->willReturn([]);

        $catalog = new GoalCatalog(
            lifeGoals: [
                'fighter.become_strongest' => [
                    'current_goal_pool' => [
                        ['code' => 'goal.participate_tournament', 'weight' => 1],
                    ],
                ],
            ],
            currentGoals: [
                'goal.participate_tournament' => [
                    'interruptible' => true,
                    'defaults'      => [],
                    'handler'       => ParticipateTournamentGoalHandler::class,
                ],
            ],
            npcLifeGoals: [],
            eventRules: [],
        );

        $provider = $this->createMock(GoalCatalogProviderInterface::class);
        $provider->expects(self::once())->method('get')->willReturn($catalog);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $clock = new SimulationClock(
            new TrainingGrowthService(),
            goalPlanner: new GoalPlanner([new ParticipateTournamentGoalHandler()]),
        );

        $handler = new AdvanceDayHandler(
            $worldRepository,
            $characterRepository,
            $npcProfiles,
            $tiles,
            $clock,
            $entityManager,
            $characterGoals,
            $characterEvents,
            $provider,
        );

        $beforeStrength = $character->getStrength();

        $handler->advance(1, 1);

        self::assertSame(1, $character->getTileX());
        self::assertSame(0, $character->getTileY());
        self::assertFalse($character->hasTravelTarget());
        self::assertSame($beforeStrength, $character->getStrength());
    }

    public function testAdvancePersistsTournamentInterestEventsWhenServiceConfigured(): void
    {
        $world = new World('seed-1');
        $character = new Character($world, 'Gohan', Race::Human);
        $character->setTilePosition(2, 0);
        $character->addMoney(1);
        $this->setEntityId($character, 7);

        $settlement = new \App\Entity\Settlement($world, 3, 0);
        $tournament = new \App\Entity\Tournament($world, $settlement, 1, 3, 200, 100, 6, 10);
        $this->setEntityId($tournament, 99);

        $worldRepository = $this->createMock(WorldRepository::class);
        $worldRepository->method('find')->with(1)->willReturn($world);

        $characterRepository = $this->createMock(CharacterRepository::class);
        $characterRepository->method('findBy')->willReturn([$character]);

        $profile = new \App\Entity\NpcProfile($character, \App\Game\Domain\Npc\NpcArchetype::Fighter);
        $npcProfiles = $this->createMock(NpcProfileRepository::class);
        $npcProfiles->method('findByWorld')->willReturn([$profile]);

        $tiles = $this->createMock(WorldMapTileRepository::class);
        $tiles->method('findBy')->willReturn([]);

        $repo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repo->method('findBy')->willReturn([$tournament]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repo);
        $persisted = [];
        $entityManager->method('persist')
            ->willReturnCallback(static function (object $event) use (&$persisted): void {
                $persisted[] = $event;
            });
        $entityManager->expects(self::exactly(2))->method('flush');

        $clock = new SimulationClock(new TrainingGrowthService());

        $characterGoals = $this->createMock(CharacterGoalRepository::class);
        $characterGoals->method('findByWorld')->willReturn([]);

        $characterEvents = $this->createMock(CharacterEventRepository::class);
        $characterEvents->method('findByWorldUpToDay')->willReturn([]);

        $goalProvider = $this->createMock(GoalCatalogProviderInterface::class);
        $goalProvider->method('get')->willReturn(new GoalCatalog([], [], [], []));

        $catalog = new \App\Game\Domain\Economy\EconomyCatalog(
            jobs: [],
            employmentPools: [],
            settlement: ['wage_pool_rate' => 0.7, 'tax_rate' => 0.2, 'production' => ['per_work_unit_base' => 10, 'per_work_unit_prosperity_mult' => 1, 'randomness_pct' => 0.1]],
            thresholds: ['money_low_employed' => 10, 'money_low_unemployed' => 5],
            tournaments: ['min_spend' => 50, 'max_spend_fraction_of_treasury' => 0.3, 'prize_pool_fraction' => 0.5, 'duration_days' => 2, 'radius' => ['base' => 2, 'per_spend' => 50, 'max' => 20], 'gains' => ['fame_base' => 1, 'fame_per_spend' => 100, 'prosperity_base' => 1, 'prosperity_per_spend' => 150, 'per_participant_fame' => 1]],
            tournamentInterest: ['commit_threshold' => 60, 'weights' => ['distance' => 30, 'prize_pool' => 25, 'archetype_bias' => 20, 'money_pressure' => 15, 'cooldown_penalty' => 20]],
        );

        $provider = new class ($catalog) implements \App\Game\Application\Economy\EconomyCatalogProviderInterface {
            public function __construct(private readonly \App\Game\Domain\Economy\EconomyCatalog $catalog)
            {
            }

            public function get(): \App\Game\Domain\Economy\EconomyCatalog
            {
                return $this->catalog;
            }
        };

        $interestService = new TournamentInterestService($entityManager, $provider);

        $handler = new AdvanceDayHandler(
            $worldRepository,
            $characterRepository,
            $npcProfiles,
            $tiles,
            $clock,
            $entityManager,
            $characterGoals,
            $characterEvents,
            $goalProvider,
            tournamentInterestService: $interestService,
        );

        $handler->advance(1, 1);

        self::assertTrue(
            (bool) array_filter(
                $persisted,
                static fn (object $event): bool => $event instanceof CharacterEvent && $event->getType() === 'tournament_interest_evaluated',
            ),
        );
    }

    private function setEntityId(object $entity, int $id): void
    {
        $ref = new \ReflectionProperty($entity::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($entity, $id);
    }
}
