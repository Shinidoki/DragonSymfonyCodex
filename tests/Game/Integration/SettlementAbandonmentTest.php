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

final class SettlementAbandonmentTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testAbandonsSettlementsWithLessThanFivePeopleLivingOrWorkingThere(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world = new World('seed-1');
        $world->setMapSize(8, 8);
        $entityManager->persist($world);

        $tile = new WorldMapTile($world, 3, 0, Biome::City);
        $tile->setHasSettlement(true);
        $entityManager->persist($tile);

        $characters = [];
        for ($i = 1; $i <= 4; $i++) {
            $c = new Character($world, sprintf('C%d', $i), Race::Human);
            $c->setTilePosition(3, 0);
            $c->setEmployment('laborer', 3, 0);
            $entityManager->persist($c);
            $characters[] = $c;
        }

        $entityManager->flush();

        $handler = self::getContainer()->get(AdvanceDayHandler::class);
        self::assertInstanceOf(AdvanceDayHandler::class, $handler);
        $handler->advance((int)$world->getId(), 3);

        $entityManager->refresh($tile);
        self::assertFalse($tile->hasSettlement());

        foreach ($characters as $character) {
            $entityManager->refresh($character);
            self::assertFalse($character->isEmployed());
        }
    }
}
