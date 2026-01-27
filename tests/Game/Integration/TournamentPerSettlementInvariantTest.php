<?php

namespace App\Tests\Game\Integration;

use App\Entity\CharacterEvent;
use App\Entity\Settlement;
use App\Entity\Tournament;
use App\Entity\World;
use App\Game\Application\Tournament\TournamentLifecycleService;
use App\Game\Domain\Economy\EconomyCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TournamentPerSettlementInvariantTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    private function economyCatalog(): EconomyCatalog
    {
        return new EconomyCatalog(
            jobs: [],
            employmentPools: [],
            settlement: [
                'wage_pool_rate' => 0.0,
                'tax_rate'       => 0.0,
                'production'     => [
                    'per_work_unit_base'            => 0,
                    'per_work_unit_prosperity_mult' => 0,
                    'randomness_pct'                => 0.0,
                ],
            ],
            thresholds: [
                'money_low_employed'   => 0,
                'money_low_unemployed' => 0,
            ],
            tournaments: [],
        );
    }

    public function testCreatesAtMostOneScheduledTournamentPerSettlement(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world = new World('seed-1');
        $entityManager->persist($world);

        $settlement = new Settlement($world, 2, 2);
        $entityManager->persist($settlement);

        $event1 = new CharacterEvent(
            world: $world,
            character: null,
            type: 'tournament_announced',
            day: 1,
            data: [
                'center_x'    => 2,
                'center_y'    => 2,
                'radius'      => 5,
                'spend'       => 100,
                'prize_pool'  => 50,
                'resolve_day' => 10,
            ],
        );
        $entityManager->persist($event1);

        $event2 = new CharacterEvent(
            world: $world,
            character: null,
            type: 'tournament_announced',
            day: 2,
            data: [
                'center_x'    => 2,
                'center_y'    => 2,
                'radius'      => 5,
                'spend'       => 100,
                'prize_pool'  => 50,
                'resolve_day' => 11,
            ],
        );
        $entityManager->persist($event2);
        $entityManager->flush();

        $service = new TournamentLifecycleService($entityManager);
        $service->advanceDay(
            world: $world,
            worldDay: 1,
            characters: [],
            goalsByCharacterId: [],
            emittedEvents: [$event1, $event2],
            settlements: [$settlement],
            economyCatalog: $this->economyCatalog(),
        );
        $entityManager->flush();

        $scheduled = $entityManager->getRepository(Tournament::class)->findBy([
            'world'      => $world,
            'settlement' => $settlement,
            'status'     => Tournament::STATUS_SCHEDULED,
        ]);
        self::assertCount(1, $scheduled);
    }
}

