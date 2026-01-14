<?php

namespace App\Tests\Game\Application\Local;

use App\Entity\LocalCombat;
use App\Entity\LocalCombatant;
use App\Entity\LocalSession;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LocalCombatantKiPersistenceTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testCombatantPersistsKiFields(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $session = new LocalSession(worldId: 1, characterId: 1, tileX: 0, tileY: 0, width: 8, height: 8, playerX: 4, playerY: 4);
        $combat  = new LocalCombat($session);
        $c       = new LocalCombatant($combat, actorId: 1, maxHp: 10, maxKi: 12);

        $entityManager->persist($session);
        $entityManager->persist($combat);
        $entityManager->persist($c);
        $entityManager->flush();
        $entityManager->clear();

        $reloaded = $entityManager->find(LocalCombatant::class, 1);
        self::assertInstanceOf(LocalCombatant::class, $reloaded);
        self::assertSame(12, $reloaded->getMaxKi());
        self::assertSame(12, $reloaded->getCurrentKi());
    }
}

