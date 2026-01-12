<?php

namespace App\Tests\Game\Application\Map;

use App\Entity\World;
use App\Entity\WorldMapTile;
use App\Game\Application\Map\GenerateWorldMapHandler;
use App\Game\Domain\Map\MapGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GenerateWorldMapHandlerTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testGeneratesAllTilesAndIsIdempotent(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world = new World('seed-1');
        $entityManager->persist($world);
        $entityManager->flush();

        $handler = new GenerateWorldMapHandler(new MapGenerator(), $entityManager);

        $first = $handler->generate((int)$world->getId(), 3, 2, 'Earth');
        self::assertSame(6, $first['total']);
        self::assertSame(6, $first['created']);

        $tileCountAfterFirst = $entityManager->getRepository(WorldMapTile::class)->count(['world' => $world]);
        self::assertSame(6, $tileCountAfterFirst);

        $second = $handler->generate((int)$world->getId(), 3, 2, 'Earth');
        self::assertSame(6, $second['total']);
        self::assertSame(0, $second['created']);

        $tileCountAfterSecond = $entityManager->getRepository(WorldMapTile::class)->count(['world' => $world]);
        self::assertSame(6, $tileCountAfterSecond);
    }
}
