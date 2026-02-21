<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\SimulationDailyKpi;
use App\Entity\World;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WorldSimulationKpiControllerTest extends WebTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testReturnsNotFoundWhenWorldMissing(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $client->request('GET', '/api/worlds/999/simulation/kpis?fromDay=1&toDay=2');

        self::assertResponseStatusCodeSame(404);
    }

    public function testValidatesDayRangeAndLimit(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world = new World('seed-kpi');
        $entityManager->persist($world);
        $entityManager->flush();

        $client->request('GET', '/api/worlds/' . $world->getId() . '/simulation/kpis?fromDay=5&toDay=4&limit=0');

        self::assertResponseStatusCodeSame(400);
    }

    public function testReturnsOrderedSnapshotsAndSummary(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world = new World('seed-kpi');
        $entityManager->persist($world);

        $entityManager->persist(new SimulationDailyKpi($world, 1, 2, 10, 2, 0.2, 1, 1, 1, 0, 40.0, 100.0));
        $entityManager->persist(new SimulationDailyKpi($world, 2, 2, 12, 3, 0.25, 0, 1, 1, 1, 45.0, 120.0));
        $entityManager->flush();

        $client->request('GET', '/api/worlds/' . $world->getId() . '/simulation/kpis?fromDay=1&toDay=2&limit=100');

        self::assertResponseIsSuccessful();
        $json = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(2, $json['summary']['sampleSize']);
        self::assertSame(1, $json['fromDay']);
        self::assertSame(2, $json['toDay']);
        self::assertCount(2, $json['snapshots']);
        self::assertSame(1, $json['snapshots'][0]['day']);
        self::assertSame(2, $json['snapshots'][1]['day']);
    }
}
