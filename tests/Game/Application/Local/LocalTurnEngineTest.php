<?php

namespace App\Tests\Game\Application\Local;

use App\Entity\Character;
use App\Entity\LocalActor;
use App\Entity\LocalIntent;
use App\Entity\LocalSession;
use App\Entity\World;
use App\Game\Application\Local\ApplyLocalActionHandler;
use App\Game\Application\Local\EnterLocalModeHandler;
use App\Game\Application\Local\LocalEventLog;
use App\Game\Domain\LocalMap\LocalAction;
use App\Game\Domain\LocalMap\LocalActionType;
use App\Game\Domain\LocalNpc\IntentType;
use App\Game\Domain\Race;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LocalTurnEngineTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testNpcMayActBeforePlayerWhenFaster(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world  = new World('seed-1');
        $player = new Character($world, 'Goku', Race::Saiyan);
        $npc    = new Character($world, 'Krillin', Race::Human);

        $player->setSpeed(10);
        $npc->setSpeed(30);

        $entityManager->persist($world);
        $entityManager->persist($player);
        $entityManager->persist($npc);
        $entityManager->flush();

        $enter   = new EnterLocalModeHandler($entityManager);
        $session = $enter->enter((int)$player->getId(), 8, 8);

        $playerActor = $entityManager->getRepository(LocalActor::class)->findOneBy([
            'session' => $session,
            'role'    => 'player',
        ]);
        self::assertInstanceOf(LocalActor::class, $playerActor);

        $session->setPlayerPosition(4, 4);
        $playerActor->setPosition(4, 4);
        $entityManager->flush();

        $npcActor = new LocalActor($session, characterId: (int)$npc->getId(), role: 'npc', x: 4, y: 7);
        $entityManager->persist($npcActor);
        $entityManager->flush();

        $initialNpcY = $npcActor->getY();

        $intent = new LocalIntent($npcActor, IntentType::MoveTo, targetActorId: (int)$playerActor->getId());
        $entityManager->persist($intent);
        $entityManager->flush();

        $handler = new ApplyLocalActionHandler($entityManager);
        $handler->apply((int)$session->getId(), new LocalAction(LocalActionType::Wait));

        $reloadedSession = $entityManager->find(LocalSession::class, (int)$session->getId());
        self::assertInstanceOf(LocalSession::class, $reloadedSession);

        // We expect at least one NPC action to have happened in the same call.
        $reloadedNpc = $entityManager->find(LocalActor::class, (int)$npcActor->getId());
        self::assertInstanceOf(LocalActor::class, $reloadedNpc);
        self::assertNotSame($initialNpcY, $reloadedNpc->getY());

        // And more than one tick can be consumed due to speed-based scheduling.
        self::assertGreaterThanOrEqual(3, $reloadedSession->getCurrentTick());

        // No nearby message is required for move intents.
        $messages = (new LocalEventLog($entityManager))->drainMessages((int)$session->getId());
        self::assertSame([], $messages);
    }
}