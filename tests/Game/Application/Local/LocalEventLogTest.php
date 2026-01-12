<?php

namespace App\Tests\Game\Application\Local;

use App\Entity\Character;
use App\Entity\World;
use App\Game\Application\Local\EnterLocalModeHandler;
use App\Game\Application\Local\LocalEventLog;
use App\Game\Domain\LocalMap\VisibilityRadius;
use App\Game\Domain\Race;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LocalEventLogTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testLogsAndDrainsEventMessagesWithinRadius(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world     = new World('seed-1');
        $character = new Character($world, 'Goku', Race::Saiyan);

        $entityManager->persist($world);
        $entityManager->persist($character);
        $entityManager->flush();

        $enter   = new EnterLocalModeHandler($entityManager);
        $session = $enter->enter((int)$character->getId(), 8, 8);
        $session->setPlayerPosition(1, 1);
        $entityManager->flush();

        $log = new LocalEventLog($entityManager);
        $log->record($session, eventX: 2, eventY: 1, message: 'Someone shouts nearby.', radius: new VisibilityRadius(2));

        self::assertSame(['Someone shouts nearby.'], $log->drainMessages((int)$session->getId()));
        self::assertSame([], $log->drainMessages((int)$session->getId()));
    }

    public function testDoesNotLogWhenOutsideRadius(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world     = new World('seed-1');
        $character = new Character($world, 'Goku', Race::Saiyan);

        $entityManager->persist($world);
        $entityManager->persist($character);
        $entityManager->flush();

        $enter   = new EnterLocalModeHandler($entityManager);
        $session = $enter->enter((int)$character->getId(), 8, 8);
        $session->setPlayerPosition(0, 0);
        $entityManager->flush();

        $log = new LocalEventLog($entityManager);
        $log->record($session, eventX: 7, eventY: 7, message: 'Far away fight.', radius: new VisibilityRadius(2));

        self::assertSame([], $log->drainMessages((int)$session->getId()));
    }
}

