<?php

namespace App\Tests\Game\Integration;

use App\Entity\Character;
use App\Entity\World;
use App\Entity\WorldMapTile;
use App\Game\Application\Map\GenerateWorldMapHandler;
use App\Game\Application\Simulation\AdvanceDayHandler;
use App\Game\Application\World\PopulateWorldHandler;
use App\Game\Domain\Map\MapGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SettlementScalingTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testPopulationCapsSettlementTilesAndPreventsAllMayors(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world = new World('seed-1');
        $entityManager->persist($world);
        $entityManager->flush();

        $map = new GenerateWorldMapHandler(new MapGenerator(), $entityManager);
        $map->generate((int)$world->getId(), 16, 16, 'Earth');

        $populator = self::getContainer()->get(PopulateWorldHandler::class);
        self::assertInstanceOf(PopulateWorldHandler::class, $populator);
        $populator->populate((int)$world->getId(), 25);

        $settlementCount = $entityManager->getRepository(WorldMapTile::class)->count([
            'world'         => $world,
            'hasSettlement' => true,
        ]);

        self::assertLessThanOrEqual(5, $settlementCount);

        $advance = self::getContainer()->get(AdvanceDayHandler::class);
        self::assertInstanceOf(AdvanceDayHandler::class, $advance);
        $advance->advance((int)$world->getId(), 1);

        /** @var list<Character> $characters */
        $characters = $entityManager->getRepository(Character::class)->findBy(['world' => $world], ['id' => 'ASC']);
        self::assertNotSame([], $characters);

        $mayors = 0;
        foreach ($characters as $character) {
            if ($character->getEmploymentJobCode() === 'mayor') {
                $mayors++;
            }
        }

        self::assertLessThan(count($characters), $mayors);
        self::assertLessThanOrEqual($settlementCount, $mayors);
    }
}

