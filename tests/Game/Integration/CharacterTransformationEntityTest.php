<?php

namespace App\Tests\Game\Integration;

use App\Entity\Character;
use App\Entity\CharacterTransformation;
use App\Entity\World;
use App\Game\Domain\Race;
use App\Game\Domain\Transformations\Transformation;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CharacterTransformationEntityTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testCharacterTransformationPersistsProficiency(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world     = new World('seed-1');
        $character = new Character($world, 'Goku', Race::Saiyan);

        $link = new CharacterTransformation($character, Transformation::SuperSaiyan, proficiency: 51);

        $entityManager->persist($world);
        $entityManager->persist($character);
        $entityManager->persist($link);
        $entityManager->flush();

        $linkId = (int)$link->getId();
        $entityManager->clear();

        $reloaded = $entityManager->find(CharacterTransformation::class, $linkId);
        self::assertInstanceOf(CharacterTransformation::class, $reloaded);
        self::assertSame(51, $reloaded->getProficiency());
        self::assertSame(Transformation::SuperSaiyan, $reloaded->getTransformation());
    }
}

