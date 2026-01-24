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
use App\Game\Domain\LocalMap\LocalAction;
use App\Game\Domain\LocalMap\LocalActionType;
use App\Game\Domain\Race;
use App\Game\Domain\Techniques\TechniqueType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TechniqueFailureAndProficiencyTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testFailureSpendsPartialKiAndDoesNotIncreaseProficiency(): void
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
            code: 'failable_blast',
            name: 'Failable Blast',
            type: TechniqueType::Blast,
            config: [
                'aimModes' => ['actor'],
                'delivery' => 'point',
                'range' => 2,
                'kiCost' => 10,
                'successChance' => ['at0' => 0.0, 'at100' => 1.0],
                'failureKiCostMultiplier' => 0.5,
                'damage' => [
                    'stat' => 'kiControl',
                    'statMultiplier' => 1.0,
                    'base' => 0,
                    'min' => 1,
                    'mitigationStat' => 'durability',
                    'mitigationDivisor' => 2,
                ],
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

        $npcActor = new LocalActor($session, characterId: (int)$npc->getId(), role: 'npc', x: 4, y: 6);
        $em->persist($npcActor);
        $em->flush();

        (new ApplyLocalActionHandler($em))->apply(
            (int)$session->getId(),
            new LocalAction(LocalActionType::Technique, targetActorId: (int)$npcActor->getId(), techniqueCode: 'failable_blast'),
        );

        $combat = $em->getRepository(LocalCombat::class)->findOneBy(['session' => $session]);
        self::assertInstanceOf(LocalCombat::class, $combat);

        $attackerCombatant = $em->getRepository(LocalCombatant::class)->findOneBy(['combat' => $combat, 'actorId' => (int)$playerActor->getId()]);
        self::assertInstanceOf(LocalCombatant::class, $attackerCombatant);
        self::assertSame($attackerCombatant->getMaxKi() - 5, $attackerCombatant->getCurrentKi());

        $reloadedKnowledge = $em->find(CharacterTechnique::class, (int)$knowledge->getId());
        self::assertInstanceOf(CharacterTechnique::class, $reloadedKnowledge);
        self::assertSame(0, $reloadedKnowledge->getProficiency());
    }

    public function testSuccessSpendsFullKiAndIncreasesProficiency(): void
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
            code: 'sure_blast',
            name: 'Sure Blast',
            type: TechniqueType::Blast,
            config: [
                'aimModes' => ['actor'],
                'delivery' => 'point',
                'range' => 2,
                'kiCost' => 10,
                'successChance' => ['at0' => 0.0, 'at100' => 1.0],
                'failureKiCostMultiplier' => 0.5,
                'damage' => [
                    'stat' => 'kiControl',
                    'statMultiplier' => 1.0,
                    'base' => 0,
                    'min' => 1,
                    'mitigationStat' => 'durability',
                    'mitigationDivisor' => 2,
                ],
            ],
            enabled: true,
            version: 1,
        );

        $em->persist($world);
        $em->persist($player);
        $em->persist($npc);
        $em->persist($technique);

        $knowledge = new CharacterTechnique($player, $technique, proficiency: 100);
        $em->persist($knowledge);

        $em->flush();

        $session = (new EnterLocalModeHandler($em))->enter((int)$player->getId(), 8, 8);
        $session->setPlayerPosition(4, 4);

        /** @var LocalActor $playerActor */
        $playerActor = $em->getRepository(LocalActor::class)->findOneBy(['session' => $session, 'role' => 'player']);
        self::assertInstanceOf(LocalActor::class, $playerActor);
        $playerActor->setPosition(4, 4);
        $em->flush();

        $npcActor = new LocalActor($session, characterId: (int)$npc->getId(), role: 'npc', x: 4, y: 6);
        $em->persist($npcActor);
        $em->flush();

        (new ApplyLocalActionHandler($em))->apply(
            (int)$session->getId(),
            new LocalAction(LocalActionType::Technique, targetActorId: (int)$npcActor->getId(), techniqueCode: 'sure_blast'),
        );

        $combat = $em->getRepository(LocalCombat::class)->findOneBy(['session' => $session]);
        self::assertInstanceOf(LocalCombat::class, $combat);

        $attackerCombatant = $em->getRepository(LocalCombatant::class)->findOneBy(['combat' => $combat, 'actorId' => (int)$playerActor->getId()]);
        self::assertInstanceOf(LocalCombatant::class, $attackerCombatant);
        self::assertSame($attackerCombatant->getMaxKi() - 10, $attackerCombatant->getCurrentKi());

        $reloadedKnowledge = $em->find(CharacterTechnique::class, (int)$knowledge->getId());
        self::assertInstanceOf(CharacterTechnique::class, $reloadedKnowledge);
        self::assertSame(100, $reloadedKnowledge->getProficiency());
    }
}
