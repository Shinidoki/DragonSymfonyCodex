<?php

namespace App\Tests\Game\Application\Local;

use App\Entity\Character;
use App\Entity\LocalActor;
use App\Entity\LocalCombatant;
use App\Entity\World;
use App\Game\Application\Local\ApplyLocalActionHandler;
use App\Game\Application\Local\EnterLocalModeHandler;
use App\Game\Domain\LocalMap\LocalAction;
use App\Game\Domain\LocalMap\LocalActionType;
use App\Game\Domain\Race;
use App\Game\Domain\Transformations\Transformation;
use App\Game\Domain\Transformations\TransformationState;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LocalCombatTransformationTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testTransformationBoostsCombatDamage(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world  = new World('seed-1');
        $player = new Character($world, 'Goku', Race::Saiyan);
        $npc    = new Character($world, 'Krillin', Race::Human);

        $player->setStrength(2);
        $player->setTransformationState(new TransformationState(
            active: Transformation::SuperSaiyan,
            activeTicks: 0,
            exhaustionDaysRemaining: 0,
        ));

        $npc->setEndurance(1);
        $npc->setDurability(1);

        $entityManager->persist($world);
        $entityManager->persist($player);
        $entityManager->persist($npc);
        $entityManager->flush();

        $session = (new EnterLocalModeHandler($entityManager))->enter((int)$player->getId(), 8, 8);

        /** @var LocalActor $playerActor */
        $playerActor = $entityManager->getRepository(LocalActor::class)->findOneBy(['session' => $session, 'role' => 'player']);
        self::assertInstanceOf(LocalActor::class, $playerActor);

        $playerActor->setPosition(4, 4);
        $session->setPlayerPosition(4, 4);
        $entityManager->flush();

        $npcActor = new LocalActor($session, characterId: (int)$npc->getId(), role: 'npc', x: 4, y: 5);
        $entityManager->persist($npcActor);
        $entityManager->flush();

        (new ApplyLocalActionHandler($entityManager))->apply(
            (int)$session->getId(),
            new LocalAction(LocalActionType::Attack, targetActorId: (int)$npcActor->getId()),
        );

        /** @var list<LocalCombatant> $combatants */
        $combatants = $entityManager->getRepository(LocalCombatant::class)->findBy([], ['id' => 'ASC']);
        self::assertNotEmpty($combatants);

        $npcCombatant = null;
        foreach ($combatants as $combatant) {
            if ($combatant->getActorId() === (int)$npcActor->getId()) {
                $npcCombatant = $combatant;
                break;
            }
        }
        self::assertInstanceOf(LocalCombatant::class, $npcCombatant);

        // With SSJ multiplier 2.0, STR=4, durability=1 => damage = max(1, 4 - 0) = 4
        self::assertSame($npcCombatant->getMaxHp() - 4, $npcCombatant->getCurrentHp());
    }
}

