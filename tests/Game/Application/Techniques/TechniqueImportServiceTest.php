<?php

namespace App\Tests\Game\Application\Techniques;

use App\Entity\TechniqueDefinition;
use App\Game\Application\Techniques\TechniqueImportService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TechniqueImportServiceTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testImportUpsertsTechniqueByCode(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $importer = new TechniqueImportService($entityManager);
        $result   = $importer->importFromJsonString(json_encode([
            'code'    => 'ki_blast',
            'name'    => 'Ki Blast',
            'type'    => 'blast',
            'enabled' => true,
            'version' => 1,
            'config' => [
                'aimModes' => ['actor', 'dir', 'point'],
                'delivery' => 'projectile',
                'kiCost'   => 3,
                'range'    => 2,
            ],
        ], JSON_THROW_ON_ERROR));

        self::assertSame(1, $result->created);
        self::assertSame(0, $result->updated);

        $result = $importer->importFromJsonString(json_encode([
            'code'    => 'ki_blast',
            'name'    => 'Ki Blast+',
            'type'    => 'blast',
            'enabled' => true,
            'version' => 2,
            'config' => [
                'aimModes' => ['actor', 'dir', 'point'],
                'delivery' => 'projectile',
                'kiCost'   => 4,
                'range'    => 2,
            ],
        ], JSON_THROW_ON_ERROR));

        self::assertSame(0, $result->created);
        self::assertSame(1, $result->updated);

        $reloaded = $entityManager->getRepository(TechniqueDefinition::class)->findOneBy(['code' => 'ki_blast']);
        self::assertInstanceOf(TechniqueDefinition::class, $reloaded);
        self::assertSame('Ki Blast+', $reloaded->getName());
        self::assertSame(4, $reloaded->getConfig()['kiCost']);
        self::assertSame(2, $reloaded->getVersion());
    }
}
