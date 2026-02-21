<?php

declare(strict_types=1);

namespace App\Tests\Game\Application\Settlement;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\Settlement;
use App\Entity\World;
use App\Game\Application\Economy\EconomyCatalogProviderInterface;
use App\Game\Application\Settlement\SettlementMigrationPressureService;
use App\Game\Domain\Economy\EconomyCatalog;
use App\Game\Domain\Race;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class SettlementMigrationPressureServiceTest extends TestCase
{
    public function testEmitsMigrationCommitEventForBestDestination(): void
    {
        $world = new World('seed-1');

        $source = new Settlement($world, 1, 1);
        $source->setProsperity(20);
        $source->addToTreasury(10);

        $destination = new Settlement($world, 3, 2);
        $destination->setProsperity(90);
        $destination->addToTreasury(250);

        $character = new Character($world, 'Traveler', Race::Human);
        $character->setTilePosition(1, 1);
        $this->setEntityId($character, 7);

        // create crowding pressure at source settlement
        $crowdA = new Character($world, 'A', Race::Human); $crowdA->setTilePosition(1, 1);
        $crowdB = new Character($world, 'B', Race::Human); $crowdB->setTilePosition(1, 1);

        $service = new SettlementMigrationPressureService(
            entityManager: $this->mockEntityManager([]),
            economyCatalogProvider: $this->provider($this->economyCatalog()),
        );

        $events = $service->advanceDay($world, 10, [$character, $crowdA, $crowdB], [$source, $destination]);

        self::assertCount(1, $events);
        self::assertSame('settlement_migration_committed', $events[0]->getType());
        self::assertSame(3, $events[0]->getData()['target_x'] ?? null);
        self::assertSame(2, $events[0]->getData()['target_y'] ?? null);
    }

    public function testSkipsCharacterDuringCooldownWindow(): void
    {
        $world = new World('seed-1');

        $source = new Settlement($world, 1, 1);
        $source->setProsperity(20);
        $destination = new Settlement($world, 3, 2);
        $destination->setProsperity(90);

        $character = new Character($world, 'Traveler', Race::Human);
        $character->setTilePosition(1, 1);
        $this->setEntityId($character, 7);

        $recentMove = new CharacterEvent(
            world: $world,
            character: $character,
            type: 'settlement_migration_committed',
            day: 9,
            data: ['target_x' => 3, 'target_y' => 2],
        );

        $service = new SettlementMigrationPressureService(
            entityManager: $this->mockEntityManager([$recentMove]),
            economyCatalogProvider: $this->provider($this->economyCatalog()),
        );

        $events = $service->advanceDay($world, 10, [$character], [$source, $destination]);

        self::assertSame([], $events);
    }

    public function testCooldownLookupIsBatchedOnceForAllCharacters(): void
    {
        $world = new World('seed-1');

        $source = new Settlement($world, 1, 1);
        $source->setProsperity(20);

        $destination = new Settlement($world, 3, 2);
        $destination->setProsperity(90);

        $characters = [];
        for ($i = 1; $i <= 3; $i++) {
            $character = new Character($world, 'Traveler-' . $i, Race::Human);
            $character->setTilePosition(1, 1);
            $this->setEntityId($character, $i);
            $characters[] = $character;
        }

        $service = new SettlementMigrationPressureService(
            entityManager: $this->mockEntityManager([], expectedFindByCalls: 1),
            economyCatalogProvider: $this->provider($this->economyCatalog()),
        );

        $events = $service->advanceDay($world, 10, $characters, [$source, $destination]);

        self::assertNotSame([], $events);
    }

    public function testSkipsEmployedCharactersAndUsesDailyCapForUnemployedCandidates(): void
    {
        $world = new World('seed-1');

        $source = new Settlement($world, 1, 1);
        $source->setProsperity(20);
        $destination = new Settlement($world, 3, 2);
        $destination->setProsperity(90);

        $employed = new Character($world, 'Employed', Race::Human);
        $employed->setTilePosition(1, 1);
        $employed->setEmployment('farmer', 1, 1);
        $this->setEntityId($employed, 7);

        $unemployed = new Character($world, 'Unemployed', Race::Human);
        $unemployed->setTilePosition(1, 1);
        $this->setEntityId($unemployed, 8);

        $service = new SettlementMigrationPressureService(
            entityManager: $this->mockEntityManager([]),
            economyCatalogProvider: $this->provider($this->economyCatalog([
                'daily_move_cap' => 1,
                'commit_threshold' => 1,
            ])),
        );

        $events = $service->advanceDay($world, 10, [$employed, $unemployed], [$source, $destination]);

        self::assertCount(1, $events);
        self::assertSame(8, $events[0]->getData()['character_id'] ?? null);
    }

    public function testLookbackDaysLimitsCooldownWindow(): void
    {
        $world = new World('seed-1');

        $source = new Settlement($world, 1, 1);
        $source->setProsperity(20);
        $destination = new Settlement($world, 3, 2);
        $destination->setProsperity(90);

        $character = new Character($world, 'Traveler', Race::Human);
        $character->setTilePosition(1, 1);
        $this->setEntityId($character, 7);

        $pastMove = new CharacterEvent(
            world: $world,
            character: $character,
            type: 'settlement_migration_committed',
            day: 5,
            data: ['target_x' => 3, 'target_y' => 2],
        );

        $service = new SettlementMigrationPressureService(
            entityManager: $this->mockEntityManager([$pastMove]),
            economyCatalogProvider: $this->provider($this->economyCatalog([
                'lookback_days' => 3,
                'move_cooldown_days' => 10,
                'commit_threshold' => 1,
            ])),
        );

        $events = $service->advanceDay($world, 10, [$character], [$source, $destination]);

        self::assertCount(1, $events);
    }

    /** @param list<CharacterEvent> $recentEvents */
    private function mockEntityManager(array $recentEvents, ?int $expectedFindByCalls = null): EntityManagerInterface
    {
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn($recentEvents);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('addOrderBy')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $eventRepo = $this->createMock(EntityRepository::class);
        if ($expectedFindByCalls !== null) {
            $eventRepo->expects(self::exactly($expectedFindByCalls))->method('createQueryBuilder')->with('e')->willReturn($queryBuilder);
        } else {
            $eventRepo->method('createQueryBuilder')->with('e')->willReturn($queryBuilder);
        }

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($eventRepo);

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

    /** @param array<string,int> $migrationOverrides */
    private function economyCatalog(array $migrationOverrides = []): EconomyCatalog
    {
        $migrationPressure = array_replace([
            'lookback_days' => 14,
            'commit_threshold' => 50,
            'move_cooldown_days' => 3,
            'daily_move_cap' => 3,
            'max_travel_distance' => 12,
        ], $migrationOverrides);

        return new EconomyCatalog(
            jobs: [],
            employmentPools: [],
            settlement: ['wage_pool_rate' => 0.7, 'tax_rate' => 0.2, 'production' => ['per_work_unit_base' => 10, 'per_work_unit_prosperity_mult' => 1, 'randomness_pct' => 0.1]],
            thresholds: ['money_low_employed' => 10, 'money_low_unemployed' => 5],
            tournaments: ['min_spend' => 50, 'max_spend_fraction_of_treasury' => 0.3, 'prize_pool_fraction' => 0.5, 'duration_days' => 2, 'radius' => ['base' => 2, 'per_spend' => 50, 'max' => 20], 'gains' => ['fame_base' => 1, 'fame_per_spend' => 100, 'prosperity_base' => 1, 'prosperity_per_spend' => 150, 'per_participant_fame' => 1]],
            tournamentInterest: [],
            migrationPressure: [
                'lookback_days' => $migrationPressure['lookback_days'],
                'commit_threshold' => $migrationPressure['commit_threshold'],
                'move_cooldown_days' => $migrationPressure['move_cooldown_days'],
                'daily_move_cap' => $migrationPressure['daily_move_cap'],
                'max_travel_distance' => $migrationPressure['max_travel_distance'],
                'weights' => [
                    'prosperity_gap' => 30,
                    'treasury_gap' => 20,
                    'crowding_gap' => 15,
                ],
            ],
        );
    }

    private function setEntityId(object $entity, int $id): void
    {
        $ref = new \ReflectionProperty($entity::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($entity, $id);
    }
}
