<?php

declare(strict_types=1);

namespace App\Tests\Game\Integration;

use App\Entity\World;
use App\Game\Application\Map\GenerateWorldMapHandler;
use App\Game\Application\Simulation\AdvanceDayHandler;
use App\Game\Application\World\CreateWorldHandler;
use App\Game\Application\World\PopulateWorldHandler;
use App\Repository\SimulationDailyKpiRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SimulationBalancingRegressionTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testLongRunSimulationPersistsKpisWithSaneInvariants(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world = self::getContainer()->get(CreateWorldHandler::class)->create('seed-balance-regression');
        self::assertInstanceOf(World::class, $world);

        self::getContainer()->get(GenerateWorldMapHandler::class)->generate((int) $world->getId(), 12, 12, 'Earth');
        self::getContainer()->get(PopulateWorldHandler::class)->populate((int) $world->getId(), 24);

        self::getContainer()->get(AdvanceDayHandler::class)->advance((int) $world->getId(), 15);

        $rows = self::getContainer()->get(SimulationDailyKpiRepository::class)
            ->findByWorldDayRange($world, 1, 15, 1000);

        self::assertCount(15, $rows);

        foreach ($rows as $row) {
            self::assertGreaterThanOrEqual(0, $row->getSettlementsActive());
            self::assertGreaterThanOrEqual(0, $row->getPopulationTotal());
            self::assertGreaterThanOrEqual(0, $row->getUnemployedCount());
            self::assertGreaterThanOrEqual(0.0, $row->getUnemploymentRate());
            self::assertLessThanOrEqual(1.0, $row->getUnemploymentRate());
            self::assertGreaterThanOrEqual(0, $row->getMigrationCommits());
            self::assertGreaterThanOrEqual(0, $row->getTournamentAnnounced());
            self::assertGreaterThanOrEqual(0, $row->getTournamentResolved());
            self::assertGreaterThanOrEqual(0, $row->getTournamentCanceled());
        }
    }
}
