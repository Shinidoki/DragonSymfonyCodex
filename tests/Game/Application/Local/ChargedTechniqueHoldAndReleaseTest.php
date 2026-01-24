<?php

namespace App\Tests\Game\Application\Local;

use App\Entity\Character;
use App\Entity\CharacterTechnique;
use App\Entity\LocalActor;
use App\Entity\LocalCombat;
use App\Entity\LocalCombatant;
use App\Entity\TechniqueDefinition;
use App\Entity\World;
use App\Game\Application\Local\ApplyLocalActionHandler;
use App\Game\Application\Local\EnterLocalModeHandler;
use App\Game\Domain\LocalMap\Direction;
use App\Game\Domain\LocalMap\LocalAction;
use App\Game\Domain\LocalMap\LocalActionType;
use App\Game\Domain\Race;
use App\Game\Domain\Techniques\TechniqueType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ChargedTechniqueHoldAndReleaseTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testChargedTechniqueCanBeHeldAndReleasedWithAim(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($em);

        $world  = new World('seed-1');
        $player = new Character($world, 'Goku', Race::Saiyan);
        $npc    = new Character($world, 'Krillin', Race::Human);

        $player->setKiCapacity(10);
        $player->setKiControl(10);
        $player->setSpeed(999);

        $npc->setDurability(1);

        $technique = new TechniqueDefinition(
            code: 'kamehameha',
            name: 'Kamehameha',
            type: TechniqueType::Charged,
            config: [
                'aimModes'               => ['dir', 'actor', 'point'],
                'delivery'               => 'ray',
                'piercing'               => 'all',
                'range'                  => 4,
                'kiCost'                 => 12,
                'chargeTicks'            => 2,
                'holdKiPerTick'          => 1,
                'allowMoveWhilePrepared' => false,
                'successChance'          => ['at0' => 1.0, 'at100' => 1.0],
            ],
            enabled: true,
            version: 1,
        );

        $em->persist($world);
        $em->persist($player);
        $em->persist($npc);
        $em->persist($technique);
        $knowledge = new CharacterTechnique($player, $technique, proficiency: 0);
        $em->persist($knowledge);
        $em->flush();

        $session = (new EnterLocalModeHandler($em))->enter((int)$player->getId(), 8, 8);
        $session->setPlayerPosition(4, 4);

        /** @var LocalActor $playerActor */
        $playerActor = $em->getRepository(LocalActor::class)->findOneBy(['session' => $session, 'role' => 'player']);
        self::assertInstanceOf(LocalActor::class, $playerActor);
        $playerActor->setPosition(4, 4);
        $em->flush();

        // Place target in a straight line south (distance 2).
        $npcActor = new LocalActor($session, characterId: (int)$npc->getId(), role: 'npc', x: 4, y: 6);
        $em->persist($npcActor);
        $em->flush();

        $handler = new ApplyLocalActionHandler($em);

        // Start charging (no aim chosen yet).
        $handler->apply((int)$session->getId(), new LocalAction(LocalActionType::Technique, techniqueCode: 'kamehameha'));

        $reloadedPlayerActor = $em->find(LocalActor::class, (int)$playerActor->getId());
        self::assertInstanceOf(LocalActor::class, $reloadedPlayerActor);
        self::assertTrue($reloadedPlayerActor->hasPreparedTechnique());
        self::assertSame(1, $reloadedPlayerActor->getPreparedTicksRemaining());

        // Continue charging for one more turn; this finishes charging and becomes ready.
        $handler->apply((int)$session->getId(), new LocalAction(LocalActionType::Wait));

        $reloadedPlayerActor = $em->find(LocalActor::class, (int)$playerActor->getId());
        self::assertInstanceOf(LocalActor::class, $reloadedPlayerActor);
        self::assertTrue($reloadedPlayerActor->hasPreparedTechnique());
        self::assertSame(0, $reloadedPlayerActor->getPreparedTicksRemaining());

        /** @var LocalCombat $combat */
        $combat = $em->getRepository(LocalCombat::class)->findOneBy(['session' => $session]);
        self::assertInstanceOf(LocalCombat::class, $combat);

        /** @var LocalCombatant $attackerCombatant */
        $attackerCombatant = $em->getRepository(LocalCombatant::class)->findOneBy(['combat' => $combat, 'actorId' => (int)$playerActor->getId()]);
        self::assertInstanceOf(LocalCombatant::class, $attackerCombatant);
        $kiBeforeHold = $attackerCombatant->getCurrentKi();

        // Holding for one turn drains holdKiPerTick.
        $handler->apply((int)$session->getId(), new LocalAction(LocalActionType::Wait));

        $attackerCombatant = $em->getRepository(LocalCombatant::class)->findOneBy(['combat' => $combat, 'actorId' => (int)$playerActor->getId()]);
        self::assertInstanceOf(LocalCombatant::class, $attackerCombatant);
        self::assertSame($kiBeforeHold - 1, $attackerCombatant->getCurrentKi());

        // Release with direction aim.
        $handler->apply((int)$session->getId(), new LocalAction(LocalActionType::Technique, direction: Direction::South, techniqueCode: 'kamehameha'));

        $reloadedKnowledge = $em->find(CharacterTechnique::class, (int)$knowledge->getId());
        self::assertInstanceOf(CharacterTechnique::class, $reloadedKnowledge);
        self::assertSame(1, $reloadedKnowledge->getProficiency());

        $attackerCombatant = $em->getRepository(LocalCombatant::class)->findOneBy(['combat' => $combat, 'actorId' => (int)$playerActor->getId()]);
        self::assertInstanceOf(LocalCombatant::class, $attackerCombatant);
        self::assertSame($kiBeforeHold - 1 - 12, $attackerCombatant->getCurrentKi());

        $defenderCombatant = $em->getRepository(LocalCombatant::class)->findOneBy(['combat' => $combat, 'actorId' => (int)$npcActor->getId()]);
        self::assertInstanceOf(LocalCombatant::class, $defenderCombatant);
        self::assertLessThan($defenderCombatant->getMaxHp(), $defenderCombatant->getCurrentHp());
    }
}

