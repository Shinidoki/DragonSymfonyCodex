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

final class TournamentCancellationTest extends KernelTestCase
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
                'wage_pool_rate' => 0.5,
                'tax_rate'       => 0.2,
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
            tournaments: [
                'min_spend'                      => 50,
                'max_spend_fraction_of_treasury' => 0.30,
                'prize_pool_fraction'            => 0.50,
                'duration_days'                  => 2,
                'radius'                         => ['base' => 2, 'per_spend' => 50, 'max' => 20],
                'gains'                          => [
                    'fame_base'            => 0,
                    'fame_per_spend'       => 100,
                    'prosperity_base'      => 0,
                    'prosperity_per_spend' => 150,
                    'per_participant_fame' => 0,
                ],
            ],
        );
    }

    public function testCancelsTournamentWhenLessThanFourParticipantsArePresent(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world = new World('seed-1');
        $entityManager->persist($world);

        $settlement = new Settlement($world, 1, 1);
        $entityManager->persist($settlement);

        $event = new CharacterEvent(
            world: $world,
            character: null,
            type: 'tournament_announced',
            day: 1,
            data: [
                'announce_day'           => 1,
                'registration_close_day' => 2,
                'center_x'               => 1,
                'center_y'               => 1,
                'radius'                 => 5,
                'spend'                  => 200,
                'prize_pool'             => 100,
                'resolve_day'            => 3,
            ],
        );
        $entityManager->persist($event);
        $entityManager->flush();

        $service = new TournamentLifecycleService($entityManager);

        $service->advanceDay(
            world: $world,
            worldDay: 1,
            characters: [],
            goalsByCharacterId: [],
            emittedEvents: [$event],
            settlements: [$settlement],
            economyCatalog: $this->economyCatalog(),
        );

        $events = $service->advanceDay(
            world: $world,
            worldDay: 2, // group stage day
            characters: [],
            goalsByCharacterId: [],
            emittedEvents: [],
            settlements: [$settlement],
            economyCatalog: $this->economyCatalog(),
        );

        self::assertNotEmpty($events);
        self::assertSame('tournament_canceled', $events[0]->getType());

        $payload = $events[0]->getData();
        self::assertIsArray($payload);
        self::assertSame('canceled', $payload['outcome'] ?? null);
        self::assertSame(0, $payload['participant_count'] ?? null);
        self::assertSame(0, $payload['registered_count'] ?? null);
        self::assertSame('insufficient_participants', $payload['reason'] ?? null);

        $tournamentRepo = $entityManager->getRepository(Tournament::class);
        $tournament     = $tournamentRepo->findOneBy(['requestEventId' => (int)$event->getId()]);
        self::assertInstanceOf(Tournament::class, $tournament);
        self::assertSame(Tournament::STATUS_CANCELED, $tournament->getStatus());
    }
}

