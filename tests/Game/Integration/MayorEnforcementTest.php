<?php

namespace App\Tests\Game\Integration;

use App\Entity\Character;
use App\Entity\World;
use App\Entity\WorldMapTile;
use App\Game\Application\Simulation\AdvanceDayHandler;
use App\Game\Domain\Map\Biome;
use App\Game\Domain\Race;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MayorEnforcementTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testEnforcesExactlyOneMayorByInfluenceThenMoney(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world = new World('seed-1');
        $world->setMapSize(8, 8);
        $entityManager->persist($world);

        $settlementTile = new WorldMapTile($world, 3, 0, Biome::City);
        $settlementTile->setHasSettlement(true);
        $entityManager->persist($settlementTile);

        $a = new Character($world, 'A', Race::Human);
        $a->setTilePosition(3, 0);
        $a->addInfluence(10);
        $a->addMoney(50);
        $a->setEmployment('laborer', 3, 0);

        $b = new Character($world, 'B', Race::Human);
        $b->setTilePosition(3, 0);
        $b->addInfluence(10);
        $b->addMoney(100);
        $b->setEmployment('laborer', 3, 0);

        $entityManager->persist($a);
        $entityManager->persist($b);
        $entityManager->flush();

        $handler = self::getContainer()->get(AdvanceDayHandler::class);
        self::assertInstanceOf(AdvanceDayHandler::class, $handler);

        $handler->advance((int)$world->getId(), 1);

        $entityManager->refresh($a);
        $entityManager->refresh($b);

        $isMayorA = $a->getEmploymentJobCode() === 'mayor';
        $isMayorB = $b->getEmploymentJobCode() === 'mayor';

        self::assertSame(1, ($isMayorA ? 1 : 0) + ($isMayorB ? 1 : 0));
        self::assertTrue($isMayorB);
    }
}
