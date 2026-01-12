<?php

namespace App\Tests\Game\Application\Local;

use App\Entity\Character;
use App\Entity\World;
use App\Game\Application\Local\ApplyLocalActionHandler;
use App\Game\Application\Local\EnterLocalModeHandler;
use App\Game\Domain\LocalMap\Direction;
use App\Game\Domain\LocalMap\LocalAction;
use App\Game\Domain\LocalMap\LocalActionType;
use App\Game\Domain\Race;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ApplyLocalActionHandlerTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testMoveUpdatesPlayerPositionAndIncrementsTick(): void
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
        $session = $enter->enter((int)$character->getId(), 3, 3);

        $session->setPlayerPosition(0, 0);
        $entityManager->flush();

        $handler = new ApplyLocalActionHandler($entityManager);
        $updated = $handler->apply((int)$session->getId(), new LocalAction(LocalActionType::Move, Direction::West));

        self::assertSame(0, $updated->getPlayerX());
        self::assertSame(0, $updated->getPlayerY());
        self::assertSame(1, $updated->getCurrentTick());
    }
}

