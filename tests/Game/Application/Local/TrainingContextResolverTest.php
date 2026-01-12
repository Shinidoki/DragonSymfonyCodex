<?php

namespace App\Tests\Game\Application\Local;

use App\Entity\World;
use App\Entity\WorldMapTile;
use App\Game\Application\Local\TrainingContextResolver;
use App\Game\Domain\Map\Biome;
use App\Game\Domain\Training\TrainingContext;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TrainingContextResolverTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testReturnsDojoWhenTileHasDojo(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world = new World('seed-1');
        $tile  = new WorldMapTile($world, 2, 3, Biome::Plains);
        $tile->setHasDojo(true);

        $entityManager->persist($world);
        $entityManager->persist($tile);
        $entityManager->flush();

        $resolver = new TrainingContextResolver($entityManager);

        self::assertSame(TrainingContext::Dojo, $resolver->forWorldTile((int)$world->getId(), 2, 3));
    }

    public function testDefaultsToWildernessWhenTileHasNoDojo(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world = new World('seed-1');
        $tile  = new WorldMapTile($world, 0, 0, Biome::Plains);

        $entityManager->persist($world);
        $entityManager->persist($tile);
        $entityManager->flush();

        $resolver = new TrainingContextResolver($entityManager);

        self::assertSame(TrainingContext::Wilderness, $resolver->forWorldTile((int)$world->getId(), 0, 0));
    }
}

