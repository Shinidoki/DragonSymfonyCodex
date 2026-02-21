<?php

namespace App\Tests\Game\Application\Techniques;

use App\Game\Application\Techniques\TechniqueImportService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TechniqueImportServiceValidationTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testImportRejectsMissingAimModes(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $importer = new TechniqueImportService($entityManager);

        $this->expectException(\InvalidArgumentException::class);
        $importer->importFromJsonString(json_encode([
            'code'    => 'bad_tech',
            'name'    => 'Bad Tech',
            'type'    => 'blast',
            'enabled' => true,
            'version' => 1,
            'config'  => [
                'delivery' => 'projectile',
                'range'    => 2,
                'kiCost'   => 3,
            ],
        ], JSON_THROW_ON_ERROR));
    }

    public function testImportRejectsEmptyAimModes(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $importer = new TechniqueImportService($entityManager);

        $this->expectException(\InvalidArgumentException::class);
        $importer->importFromJsonString(json_encode([
            'code'    => 'bad_tech',
            'name'    => 'Bad Tech',
            'type'    => 'blast',
            'enabled' => true,
            'version' => 1,
            'config'  => [
                'aimModes' => [],
                'delivery' => 'projectile',
                'range'    => 2,
                'kiCost'   => 3,
            ],
        ], JSON_THROW_ON_ERROR));
    }

    public function testImportRejectsInvalidDelivery(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $importer = new TechniqueImportService($entityManager);

        $this->expectException(\InvalidArgumentException::class);
        $importer->importFromJsonString(json_encode([
            'code'    => 'bad_tech',
            'name'    => 'Bad Tech',
            'type'    => 'blast',
            'enabled' => true,
            'version' => 1,
            'config'  => [
                'aimModes' => ['dir'],
                'delivery' => 'nope',
                'range'    => 2,
                'kiCost'   => 3,
            ],
        ], JSON_THROW_ON_ERROR));
    }

    public function testImportRejectsAoeMissingRadius(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $importer = new TechniqueImportService($entityManager);

        $this->expectException(\InvalidArgumentException::class);
        $importer->importFromJsonString(json_encode([
            'code'    => 'bad_tech',
            'name'    => 'Bad Tech',
            'type'    => 'blast',
            'enabled' => true,
            'version' => 1,
            'config'  => [
                'aimModes' => ['actor'],
                'delivery' => 'aoe',
                'range'    => 2,
                'kiCost'   => 3,
            ],
        ], JSON_THROW_ON_ERROR));
    }

    public function testImportRejectsLegacyProjectileDelivery(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $importer = new TechniqueImportService($entityManager);

        $this->expectException(\InvalidArgumentException::class);
        $importer->importFromJsonString(json_encode([
            'code'    => 'bad_tech',
            'name'    => 'Bad Tech',
            'type'    => 'blast',
            'enabled' => true,
            'version' => 1,
            'config'  => [
                'aimModes' => ['actor'],
                'delivery' => 'projectile',
                'range'    => 2,
                'kiCost'   => 3,
            ],
        ], JSON_THROW_ON_ERROR));
    }

    public function testImportRejectsDirectionalAimMode(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $importer = new TechniqueImportService($entityManager);

        $this->expectException(\InvalidArgumentException::class);
        $importer->importFromJsonString(json_encode([
            'code'    => 'bad_tech',
            'name'    => 'Bad Tech',
            'type'    => 'blast',
            'enabled' => true,
            'version' => 1,
            'config'  => [
                'aimModes' => ['dir'],
                'delivery' => 'single',
                'range'    => 2,
                'kiCost'   => 3,
            ],
        ], JSON_THROW_ON_ERROR));
    }

    public function testImportRejectsChargedMissingChargeTicks(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $importer = new TechniqueImportService($entityManager);

        $this->expectException(\InvalidArgumentException::class);
        $importer->importFromJsonString(json_encode([
            'code'    => 'bad_tech',
            'name'    => 'Bad Tech',
            'type'    => 'charged',
            'enabled' => true,
            'version' => 1,
            'config'  => [
                'aimModes' => ['self'],
                'delivery' => 'point',
                'range'    => 0,
                'kiCost'   => 0,
            ],
        ], JSON_THROW_ON_ERROR));
    }
}
