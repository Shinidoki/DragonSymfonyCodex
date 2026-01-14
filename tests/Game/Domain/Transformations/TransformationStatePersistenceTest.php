<?php

namespace App\Tests\Game\Domain\Transformations;

use App\Entity\Character;
use App\Entity\World;
use App\Game\Domain\Race;
use App\Game\Domain\Transformations\Transformation;
use App\Game\Domain\Transformations\TransformationState;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TransformationStatePersistenceTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testCharacterPersistsTransformationState(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world     = new World('seed-1');
        $character = new Character($world, 'Goku', Race::Saiyan);
        $character->setTransformationState(new TransformationState(
            active: Transformation::SuperSaiyan,
            activeTicks: 2,
            exhaustionDaysRemaining: 1,
        ));

        $entityManager->persist($world);
        $entityManager->persist($character);
        $entityManager->flush();

        $characterId = (int)$character->getId();
        $entityManager->clear();

        $reloaded = $entityManager->find(Character::class, $characterId);
        self::assertInstanceOf(Character::class, $reloaded);

        $state = $reloaded->getTransformationState();
        self::assertSame(Transformation::SuperSaiyan, $state->active);
        self::assertSame(2, $state->activeTicks);
        self::assertSame(1, $state->exhaustionDaysRemaining);
    }
}

