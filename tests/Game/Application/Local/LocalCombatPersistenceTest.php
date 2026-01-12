<?php

namespace App\Tests\Game\Application\Local;

use App\Entity\Character;
use App\Entity\LocalActor;
use App\Entity\LocalCombat;
use App\Entity\LocalCombatant;
use App\Entity\World;
use App\Game\Application\Local\EnterLocalModeHandler;
use App\Game\Domain\Race;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LocalCombatPersistenceTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testCombatAndCombatantsPersist(): void
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

        $session = (new EnterLocalModeHandler($entityManager))->enter((int)$player->getId(), 8, 8);

        $playerActor = $entityManager->getRepository(LocalActor::class)->findOneBy([
            'session' => $session,
            'role'    => 'player',
        ]);
        self::assertInstanceOf(LocalActor::class, $playerActor);

        $npcActor = new LocalActor($session, characterId: (int)$npc->getId(), role: 'npc', x: 4, y: 5);
        $entityManager->persist($npcActor);
        $entityManager->flush();

        $combat = new LocalCombat($session);
        $entityManager->persist($combat);
        $entityManager->flush();

        $c1 = new LocalCombatant($combat, actorId: (int)$playerActor->getId(), maxHp: 13);
        $c2 = new LocalCombatant($combat, actorId: (int)$npcActor->getId(), maxHp: 13);
        $entityManager->persist($c1);
        $entityManager->persist($c2);
        $entityManager->flush();

        $combatId = (int)$combat->getId();
        $entityManager->clear();

        $reloadedCombat = $entityManager->find(LocalCombat::class, $combatId);
        self::assertInstanceOf(LocalCombat::class, $reloadedCombat);

        $combatants = $entityManager->getRepository(LocalCombatant::class)->findBy(['combat' => $reloadedCombat], ['id' => 'ASC']);
        self::assertCount(2, $combatants);
        self::assertSame((int)$playerActor->getId(), $combatants[0]->getActorId());
    }
}

