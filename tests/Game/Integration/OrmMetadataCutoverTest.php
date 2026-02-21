<?php

declare(strict_types=1);

namespace App\Tests\Game\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class OrmMetadataCutoverTest extends KernelTestCase
{
    public function testDoctrineMetadataContainsNoLocalEntitiesOrTables(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $metadata      = $entityManager->getMetadataFactory()->getAllMetadata();

        $localEntityClasses = [];
        $localTables        = [];

        foreach ($metadata as $classMetadata) {
            $className = $classMetadata->getName();
            if (str_starts_with($className, 'App\\Entity\\Local')) {
                $localEntityClasses[] = $className;
            }

            $tableName = (string)$classMetadata->getTableName();
            if (str_starts_with($tableName, 'local_')) {
                $localTables[] = $tableName;
            }
        }

        self::assertSame([], $localEntityClasses, 'Local ORM entity metadata must be removed after hard cutover.');
        self::assertSame([], $localTables, 'local_* ORM table mappings must be removed after hard cutover.');
    }
}
