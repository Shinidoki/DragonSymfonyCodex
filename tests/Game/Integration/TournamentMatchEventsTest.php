<?php

namespace App\Tests\Game\Integration;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\Settlement;
use App\Entity\Tournament;
use App\Entity\TournamentParticipant;
use App\Entity\World;
use App\Game\Application\Tournament\TournamentLifecycleService;
use App\Game\Domain\Economy\EconomyCatalog;
use App\Game\Domain\Race;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TournamentMatchEventsTest extends KernelTestCase
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
            tournaments: [
                'min_spend'                      => 0,
                'max_spend_fraction_of_treasury' => 1.0,
                'prize_pool_fraction'            => 0.0,
                'duration_days'                  => 1,
                'radius'                         => ['base' => 0, 'per_spend' => 1, 'max' => 0],
                'gains'                          => [
                    'fame_base'            => 0,
                    'fame_per_spend'       => 0,
                    'prosperity_base'      => 0,
                    'prosperity_per_spend' => 0,
                    'per_participant_fame' => 0,
                ],
            ],
        );
    }

    public function testKnockoutEmitsFightResolvedEvents(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world = new World('seed-1');
        $entityManager->persist($world);

        $settlement = new Settlement($world, 1, 1);
        $entityManager->persist($settlement);

        // Set announceDay == resolveDay to skip group stage and go straight to knockout.
        $tournament = new Tournament($world, $settlement, announceDay: 1, resolveDay: 1, spend: 0, prizePool: 0, radius: 0, requestEventId: null);
        $entityManager->persist($tournament);

        $a = new Character($world, 'A', Race::Human);
        $b = new Character($world, 'B', Race::Human);
        $a->setTilePosition(1, 1);
        $b->setTilePosition(1, 1);
        $entityManager->persist($a);
        $entityManager->persist($b);
        $entityManager->flush();

        $pa = new TournamentParticipant($tournament, $a, 0);
        $pa->setSeed(1);
        $entityManager->persist($pa);

        $pb = new TournamentParticipant($tournament, $b, 0);
        $pb->setSeed(2);
        $entityManager->persist($pb);

        $entityManager->flush();

        $service = new TournamentLifecycleService($entityManager);

        $events = $service->advanceDay(
            world: $world,
            worldDay: 1,
            characters: [$a, $b],
            goalsByCharacterId: [],
            emittedEvents: [],
            settlements: [$settlement],
            economyCatalog: $this->economyCatalog(),
        );

        $types = array_map(static fn(CharacterEvent $e): string => $e->getType(), $events);

        self::assertContains('tournament_resolved', $types);
        self::assertContains('sim_fight_resolved', $types);
    }
}
