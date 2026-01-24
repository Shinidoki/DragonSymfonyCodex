<?php

namespace App\Tests\Game\Application\World;

use App\Entity\Character;
use App\Entity\CharacterGoal;
use App\Entity\NpcProfile;
use App\Entity\World;
use App\Game\Application\Map\GenerateWorldMapHandler;
use App\Game\Application\World\PopulateWorldHandler;
use App\Game\Domain\Map\MapGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PopulateWorldHandlerTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testPopulatesWorldWithNpcProfiles(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world = new World('seed-1');
        $entityManager->persist($world);
        $entityManager->flush();

        $map = new GenerateWorldMapHandler(new MapGenerator(), $entityManager);
        $map->generate((int)$world->getId(), 8, 8, 'Earth');

        $handler = self::getContainer()->get(PopulateWorldHandler::class);
        $result  = $handler->populate((int)$world->getId(), 10);

        self::assertSame(10, $result->created);
        self::assertSame(10, array_sum($result->createdByArchetype));

        self::assertSame(10, $entityManager->getRepository(Character::class)->count(['world' => $world]));
        self::assertSame(10, $entityManager->getRepository(NpcProfile::class)->count([]));
        self::assertSame(10, $entityManager->getRepository(CharacterGoal::class)->count([]));

        /** @var list<CharacterGoal> $goals */
        $goals = $entityManager->getRepository(CharacterGoal::class)->findAll();
        foreach ($goals as $goal) {
            self::assertNotSame('', trim((string)$goal->getLifeGoalCode()));
        }

        /** @var list<Character> $characters */
        $characters = $entityManager->getRepository(Character::class)->findBy(['world' => $world]);
        foreach ($characters as $character) {
            self::assertNotNull($character->getId());
            self::assertNotSame('', trim($character->getName()));
            self::assertGreaterThanOrEqual(0, $character->getTileX());
            self::assertLessThan(8, $character->getTileX());
            self::assertGreaterThanOrEqual(0, $character->getTileY());
            self::assertLessThan(8, $character->getTileY());
        }
    }
}
