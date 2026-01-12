<?php

namespace App\Tests\Game\Application\Local;

use App\Entity\Character;
use App\Entity\LocalActor;
use App\Entity\LocalIntent;
use App\Entity\World;
use App\Game\Application\Local\EnterLocalModeHandler;
use App\Game\Domain\LocalNpc\IntentType;
use App\Game\Domain\Race;
use App\Repository\LocalActorRepository;
use App\Repository\LocalIntentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LocalIntentTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testCanPersistAndLoadIntentForActor(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world  = new World('seed-1');
        $player = new Character($world, 'Goku', Race::Saiyan);
        $npc    = new Character($world, 'Krillin', Race::Human);

        $entityManager->persist($world);
        $entityManager->persist($player);
        $entityManager->persist($npc);
        $entityManager->flush();

        $enter   = new EnterLocalModeHandler($entityManager);
        $session = $enter->enter((int)$player->getId(), 8, 8);

        /** @var LocalActorRepository $actors */
        $actors      = $entityManager->getRepository(LocalActor::class);
        $playerActor = $actors->findOneBy(['session' => $session, 'characterId' => (int)$player->getId()]);
        self::assertInstanceOf(LocalActor::class, $playerActor);

        $npcActor = new LocalActor($session, characterId: (int)$npc->getId(), role: 'npc', x: 0, y: 0);
        $entityManager->persist($npcActor);
        $entityManager->flush();

        $intent = new LocalIntent($npcActor, IntentType::TalkTo, targetActorId: (int)$playerActor->getId());
        $entityManager->persist($intent);
        $entityManager->flush();

        /** @var LocalIntentRepository $intents */
        $intents = $entityManager->getRepository(LocalIntent::class);
        $loaded  = $intents->findActiveForActor($npcActor);

        self::assertInstanceOf(LocalIntent::class, $loaded);
        self::assertSame(IntentType::TalkTo, $loaded->getType());
        self::assertSame((int)$playerActor->getId(), $loaded->getTargetActorId());
    }
}

