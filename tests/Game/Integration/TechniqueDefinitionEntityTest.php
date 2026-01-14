<?php

namespace App\Tests\Game\Integration;

use App\Entity\TechniqueDefinition;
use App\Game\Domain\Techniques\TechniqueType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TechniqueDefinitionEntityTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testTechniqueDefinitionPersistsConfig(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $technique = new TechniqueDefinition(
            code: 'ki_blast',
            name: 'Ki Blast',
            type: TechniqueType::Blast,
            config: ['kiCost' => 3, 'range' => 2],
            enabled: true,
            version: 1,
        );

        $entityManager->persist($technique);
        $entityManager->flush();
        $entityManager->clear();

        $reloaded = $entityManager->getRepository(TechniqueDefinition::class)->findOneBy(['code' => 'ki_blast']);
        self::assertInstanceOf(TechniqueDefinition::class, $reloaded);
        self::assertSame(TechniqueType::Blast, $reloaded->getType());
        self::assertSame(3, $reloaded->getConfig()['kiCost']);
    }
}

