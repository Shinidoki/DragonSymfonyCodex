<?php

declare(strict_types=1);

namespace App\Tests\Game\Application\Tournament;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\CharacterGoal;
use App\Entity\NpcProfile;
use App\Entity\Settlement;
use App\Entity\Tournament;
use App\Entity\World;
use App\Game\Application\Economy\EconomyCatalogProviderInterface;
use App\Game\Application\Tournament\TournamentInterestService;
use App\Game\Domain\Economy\EconomyCatalog;
use App\Game\Domain\Npc\NpcArchetype;
use App\Game\Domain\Race;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class TournamentInterestServiceTest extends TestCase
{
    public function testCommitsWhenScoreMeetsThreshold(): void
    {
        $world = new World('seed-1');
        $settlement = new Settlement($world, 3, 0);
        $tournament = new Tournament($world, $settlement, 1, 3, 200, 100, 6, 10);
        $this->setEntityId($tournament, 99);

        $fighter = new Character($world, 'Fighter', Race::Human);
        $fighter->setTilePosition(2, 0);
        $fighter->addMoney(1);
        $this->setEntityId($fighter, 7);

        $goal = new CharacterGoal($fighter);
        $profile = new NpcProfile($fighter, NpcArchetype::Fighter);

        $service = new TournamentInterestService(
            $this->mockEntityManager([$tournament]),
            $this->provider($this->economyCatalog(60)),
        );

        $events = $service->advanceDay(
            world: $world,
            worldDay: 1,
            characters: [$fighter],
            goalsByCharacterId: [7 => $goal],
            npcProfilesByCharacterId: [7 => $profile],
        );

        self::assertNotEmpty(array_filter($events, static fn (CharacterEvent $e): bool => $e->getType() === 'tournament_interest_evaluated'));
        $commit = array_values(array_filter($events, static fn (CharacterEvent $e): bool => $e->getType() === 'tournament_interest_committed'));
        self::assertCount(1, $commit);
        self::assertSame($fighter, $commit[0]->getCharacter());
        self::assertSame(99, $commit[0]->getData()['tournament_id'] ?? null);
    }

    public function testDoesNotCommitWhenRegistrationWindowMissed(): void
    {
        $world = new World('seed-1');
        $settlement = new Settlement($world, 3, 0);
        $tournament = new Tournament($world, $settlement, 1, 3, 200, 100, 6, 10);
        $this->setEntityId($tournament, 99);

        $fighter = new Character($world, 'Fighter', Race::Human);
        $fighter->setTilePosition(0, 0);
        $this->setEntityId($fighter, 7);

        $goal = new CharacterGoal($fighter);
        $profile = new NpcProfile($fighter, NpcArchetype::Fighter);

        $service = new TournamentInterestService(
            $this->mockEntityManager([$tournament]),
            $this->provider($this->economyCatalog(10)),
        );

        $events = $service->advanceDay(
            world: $world,
            worldDay: 4,
            characters: [$fighter],
            goalsByCharacterId: [7 => $goal],
            npcProfilesByCharacterId: [7 => $profile],
        );

        self::assertSame([], array_values(array_filter($events, static fn (CharacterEvent $e): bool => $e->getType() === 'tournament_interest_committed')));
        $evaluated = array_values(array_filter($events, static fn (CharacterEvent $e): bool => $e->getType() === 'tournament_interest_evaluated'));
        self::assertCount(1, $evaluated);
        self::assertSame('registration_closed', $evaluated[0]->getData()['reason_code'] ?? null);
    }

    public function testCommitsToOnlyOneTournamentWhenMultipleQualify(): void
    {
        $world = new World('seed-1');

        $nearSettlement = new Settlement($world, 2, 0);
        $nearTournament = new Tournament($world, $nearSettlement, 1, 3, 200, 100, 6, 10);
        $this->setEntityId($nearTournament, 10);

        $farSettlement = new Settlement($world, 7, 0);
        $farTournament = new Tournament($world, $farSettlement, 1, 3, 200, 100, 10, 11);
        $this->setEntityId($farTournament, 11);

        $fighter = new Character($world, 'Fighter', Race::Human);
        $fighter->setTilePosition(2, 0);
        $fighter->addMoney(1);
        $this->setEntityId($fighter, 7);

        $goal = new CharacterGoal($fighter);
        $profile = new NpcProfile($fighter, NpcArchetype::Fighter);

        $service = new TournamentInterestService(
            $this->mockEntityManager([$nearTournament, $farTournament]),
            $this->provider($this->economyCatalog(30)),
        );

        $events = $service->advanceDay(
            world: $world,
            worldDay: 1,
            characters: [$fighter],
            goalsByCharacterId: [7 => $goal],
            npcProfilesByCharacterId: [7 => $profile],
        );

        $evaluated = array_values(array_filter($events, static fn (CharacterEvent $e): bool => $e->getType() === 'tournament_interest_evaluated'));
        $committed = array_values(array_filter($events, static fn (CharacterEvent $e): bool => $e->getType() === 'tournament_interest_committed'));

        self::assertCount(2, $evaluated);
        self::assertCount(1, $committed);
        self::assertSame(10, $committed[0]->getData()['tournament_id'] ?? null);
    }


    public function testAppliesCooldownPenaltyWhenRecentlyCommitted(): void
    {
        $world = new World('seed-1');
        $settlement = new Settlement($world, 3, 0);
        $tournament = new Tournament($world, $settlement, 1, 3, 200, 100, 6, 10);
        $this->setEntityId($tournament, 99);

        $fighter = new Character($world, 'Fighter', Race::Human);
        $fighter->setTilePosition(2, 0);
        $fighter->addMoney(1);
        $this->setEntityId($fighter, 7);

        $recentCommit = new CharacterEvent(
            world: $world,
            character: $fighter,
            type: 'tournament_interest_committed',
            day: 0,
            data: ['tournament_id' => 42],
        );

        $goal = new CharacterGoal($fighter);
        $profile = new NpcProfile($fighter, NpcArchetype::Fighter);

        $service = new TournamentInterestService(
            $this->mockEntityManager([$tournament], [$recentCommit]),
            $this->provider($this->economyCatalog(70)),
        );

        $events = $service->advanceDay(
            world: $world,
            worldDay: 1,
            characters: [$fighter],
            goalsByCharacterId: [7 => $goal],
            npcProfilesByCharacterId: [7 => $profile],
        );

        $evaluated = array_values(array_filter($events, static fn (CharacterEvent $e): bool => $e->getType() === 'tournament_interest_evaluated'));
        self::assertCount(1, $evaluated);
        self::assertSame(-20, $evaluated[0]->getData()['factors']['cooldown_penalty'] ?? null);

        self::assertCount(
            0,
            array_values(array_filter($events, static fn (CharacterEvent $e): bool => $e->getType() === 'tournament_interest_committed')),
            'Cooldown penalty should lower score below commit threshold.',
        );
    }

    private function economyCatalog(int $threshold): EconomyCatalog
    {
        return new EconomyCatalog(
            jobs: [],
            employmentPools: [],
            settlement: ['wage_pool_rate' => 0.7, 'tax_rate' => 0.2, 'production' => ['per_work_unit_base' => 10, 'per_work_unit_prosperity_mult' => 1, 'randomness_pct' => 0.1]],
            thresholds: ['money_low_employed' => 10, 'money_low_unemployed' => 5],
            tournaments: ['min_spend' => 50, 'max_spend_fraction_of_treasury' => 0.3, 'prize_pool_fraction' => 0.5, 'duration_days' => 2, 'radius' => ['base' => 2, 'per_spend' => 50, 'max' => 20], 'gains' => ['fame_base' => 1, 'fame_per_spend' => 100, 'prosperity_base' => 1, 'prosperity_per_spend' => 150, 'per_participant_fame' => 1]],
            tournamentInterest: ['commit_threshold' => $threshold, 'weights' => ['distance' => 30, 'prize_pool' => 25, 'archetype_bias' => 20, 'money_pressure' => 15, 'cooldown_penalty' => 20]],
        );
    }

    /**
     * @param list<Tournament> $tournaments
     * @param list<CharacterEvent> $recentEvents
     */
    private function mockEntityManager(array $tournaments, array $recentEvents = []): EntityManagerInterface
    {
        $tournamentRepo = $this->createMock(EntityRepository::class);
        $tournamentRepo->method('findBy')->willReturn($tournaments);

        $eventRepo = $this->createMock(EntityRepository::class);
        $eventRepo->method('findBy')->willReturn($recentEvents);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            static fn (string $class): EntityRepository => $class === CharacterEvent::class ? $eventRepo : $tournamentRepo,
        );

        return $em;
    }

    private function provider(EconomyCatalog $catalog): EconomyCatalogProviderInterface
    {
        return new class ($catalog) implements EconomyCatalogProviderInterface {
            public function __construct(private readonly EconomyCatalog $catalog)
            {
            }

            public function get(): EconomyCatalog
            {
                return $this->catalog;
            }
        };
    }

    private function setEntityId(object $entity, int $id): void
    {
        $ref = new \ReflectionProperty($entity::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($entity, $id);
    }
}
