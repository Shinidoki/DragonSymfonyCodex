<?php

namespace App\Tests\Game\Integration;

use App\Entity\Character;
use App\Entity\CharacterTechnique;
use App\Entity\TechniqueDefinition;
use App\Entity\World;
use App\Game\Domain\Race;
use App\Game\Domain\Techniques\TechniqueType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CharacterTechniqueEntityTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testCharacterTechniquePersistsProficiency(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world     = new World('seed-1');
        $character = new Character($world, 'Goku', Race::Saiyan);
        $technique = new TechniqueDefinition(
            code: 'ki_blast',
            name: 'Ki Blast',
            type: TechniqueType::Blast,
            config: ['kiCost' => 3, 'range' => 2],
            enabled: true,
            version: 1,
        );

        $link = new CharacterTechnique($character, $technique, proficiency: 7);

        $entityManager->persist($world);
        $entityManager->persist($technique);
        $entityManager->persist($link);
        $entityManager->flush();

        $linkId = (int)$link->getId();
        $entityManager->clear();

        $reloaded = $entityManager->find(CharacterTechnique::class, $linkId);
        self::assertInstanceOf(CharacterTechnique::class, $reloaded);
        self::assertSame(7, $reloaded->getProficiency());
        self::assertSame('ki_blast', $reloaded->getTechnique()->getCode());
    }
}

