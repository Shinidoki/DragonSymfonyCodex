<?php

namespace App\Tests\Game\Application\Local;

use App\Entity\Character;
use App\Entity\LocalActor;
use App\Entity\LocalIntent;
use App\Entity\World;
use App\Game\Application\Local\EnterLocalModeHandler;
use App\Game\Domain\LocalNpc\IntentType;
use App\Game\Domain\Race;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LocalIntentFlowTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testAddNpcActorAndSetIntentPersists(): void
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

        $playerActor = $entityManager->getRepository(LocalActor::class)->findOneBy([
            'session'     => $session,
            'characterId' => (int)$player->getId(),
        ]);
        self::assertInstanceOf(LocalActor::class, $playerActor);

        $npcActor = new LocalActor($session, characterId: (int)$npc->getId(), role: 'npc', x: 0, y: 0);
        $entityManager->persist($npcActor);
        $entityManager->flush();

        $intent = new LocalIntent($npcActor, IntentType::TalkTo, targetActorId: (int)$playerActor->getId());
        $entityManager->persist($intent);
        $entityManager->flush();

        $count = $entityManager->getRepository(LocalIntent::class)->count(['actor' => $npcActor]);
        self::assertSame(1, $count);
    }
}

