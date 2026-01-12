<?php

namespace App\Tests\Game\Application\Local;

use App\Entity\Character;
use App\Entity\LocalActor;
use App\Entity\LocalCombat;
use App\Entity\LocalCombatant;
use App\Entity\LocalSession;
use App\Entity\World;
use App\Game\Application\Local\ApplyLocalActionHandler;
use App\Game\Application\Local\EnterLocalModeHandler;
use App\Game\Application\Local\LocalEventLog;
use App\Game\Domain\LocalMap\LocalAction;
use App\Game\Domain\LocalMap\LocalActionType;
use App\Game\Domain\Race;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LocalCombatFlowTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testAttackCreatesCombatAndDefeatsTargetDeterministically(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world  = new World('seed-1');
        $player = new Character($world, 'Goku', Race::Saiyan);
        $npc    = new Character($world, 'Krillin', Race::Human);

        $player->setSpeed(999);
        $player->setStrength(20);

        $npc->setSpeed(1);
        $npc->setEndurance(1);
        $npc->setDurability(1);

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

        $session->setPlayerPosition(4, 4);
        $playerActor->setPosition(4, 4);
        $entityManager->flush();

        $npcActor = new LocalActor($session, characterId: (int)$npc->getId(), role: 'npc', x: 4, y: 5);
        $entityManager->persist($npcActor);
        $entityManager->flush();

        $handler = new ApplyLocalActionHandler($entityManager);
        $handler->apply((int)$session->getId(), new LocalAction(LocalActionType::Attack, targetActorId: (int)$npcActor->getId()));

        $reloadedSession = $entityManager->find(LocalSession::class, (int)$session->getId());
        self::assertInstanceOf(LocalSession::class, $reloadedSession);
        self::assertSame(1, $reloadedSession->getCurrentTick());

        $combat = $entityManager->getRepository(LocalCombat::class)->findOneBy(['session' => $reloadedSession]);
        self::assertInstanceOf(LocalCombat::class, $combat);
        self::assertSame('resolved', $combat->getStatus());
        self::assertNotNull($combat->getEndedAt());

        /** @var list<LocalCombatant> $combatants */
        $combatants = $entityManager->getRepository(LocalCombatant::class)->findBy(['combat' => $combat], ['id' => 'ASC']);
        self::assertCount(2, $combatants);

        $defender = null;
        foreach ($combatants as $combatant) {
            if ($combatant->getActorId() === (int)$npcActor->getId()) {
                $defender = $combatant;
                break;
            }
        }
        self::assertInstanceOf(LocalCombatant::class, $defender);
        self::assertSame(0, $defender->getCurrentHp());
        self::assertSame(1, $defender->getDefeatedAtTick());

        $messages = (new LocalEventLog($entityManager))->drainMessages((int)$session->getId());
        self::assertNotEmpty($messages);
        self::assertTrue((bool)preg_grep('/attacks .* damage\\./', $messages));
        self::assertTrue((bool)preg_grep('/defeats .*\\./', $messages));
    }
}
