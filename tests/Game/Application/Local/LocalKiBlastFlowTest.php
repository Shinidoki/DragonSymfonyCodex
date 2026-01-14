<?php

namespace App\Tests\Game\Application\Local;

use App\Entity\Character;
use App\Entity\LocalActor;
use App\Entity\LocalCombat;
use App\Entity\LocalCombatant;
use App\Entity\World;
use App\Game\Application\Local\ApplyLocalActionHandler;
use App\Game\Application\Local\EnterLocalModeHandler;
use App\Game\Application\Local\LocalEventLog;
use App\Game\Domain\LocalMap\LocalAction;
use App\Game\Domain\LocalMap\LocalActionType;
use App\Game\Domain\Race;
use App\Game\Domain\Techniques\Technique;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LocalKiBlastFlowTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testKiBlastSpendsKiAndDealsDamageAtRange(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world  = new World('seed-1');
        $player = new Character($world, 'Goku', Race::Saiyan);
        $npc    = new Character($world, 'Krillin', Race::Human);

        $player->setKiCapacity(5);
        $player->setKiControl(5);
        $player->setSpeed(999);

        $npc->setEndurance(1);
        $npc->setDurability(1);

        $entityManager->persist($world);
        $entityManager->persist($player);
        $entityManager->persist($npc);
        $entityManager->flush();

        $session = (new EnterLocalModeHandler($entityManager))->enter((int)$player->getId(), 8, 8);
        $session->setPlayerPosition(4, 4);

        /** @var LocalActor $playerActor */
        $playerActor = $entityManager->getRepository(LocalActor::class)->findOneBy(['session' => $session, 'role' => 'player']);
        self::assertInstanceOf(LocalActor::class, $playerActor);

        $playerActor->setPosition(4, 4);
        $entityManager->flush();

        // Range 2: target at (4,6) is distance 2
        $npcActor = new LocalActor($session, characterId: (int)$npc->getId(), role: 'npc', x: 4, y: 6);
        $entityManager->persist($npcActor);
        $entityManager->flush();

        (new ApplyLocalActionHandler($entityManager))->apply(
            (int)$session->getId(),
            new LocalAction(LocalActionType::Technique, targetActorId: (int)$npcActor->getId(), technique: Technique::KiBlast),
        );

        $combat = $entityManager->getRepository(LocalCombat::class)->findOneBy(['session' => $session]);
        self::assertInstanceOf(LocalCombat::class, $combat);

        $attackerCombatant = $entityManager->getRepository(LocalCombatant::class)->findOneBy(['combat' => $combat, 'actorId' => (int)$playerActor->getId()]);
        self::assertInstanceOf(LocalCombatant::class, $attackerCombatant);
        self::assertSame($attackerCombatant->getMaxKi() - 3, $attackerCombatant->getCurrentKi());

        $defenderCombatant = $entityManager->getRepository(LocalCombatant::class)->findOneBy(['combat' => $combat, 'actorId' => (int)$npcActor->getId()]);
        self::assertInstanceOf(LocalCombatant::class, $defenderCombatant);
        self::assertSame($defenderCombatant->getMaxHp() - 5, $defenderCombatant->getCurrentHp());

        $messages = (new LocalEventLog($entityManager))->drainMessages((int)$session->getId());
        self::assertTrue((bool)preg_grep('/ki blast/i', $messages));
    }
}
