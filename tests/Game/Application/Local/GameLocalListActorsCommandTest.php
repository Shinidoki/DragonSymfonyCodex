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
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class GameLocalListActorsCommandTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testListsActorsWithTurnMeterAndHpWhenCombatExists(): void
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
        $playerActor->setTurnMeter(12);

        $npcActor = new LocalActor($session, characterId: (int)$npc->getId(), role: 'npc', x: 0, y: 1);
        $npcActor->setTurnMeter(34);
        $entityManager->persist($npcActor);

        $combat = new LocalCombat($session);
        $entityManager->persist($combat);
        $entityManager->flush();

        $entityManager->persist(new LocalCombatant($combat, actorId: (int)$playerActor->getId(), maxHp: 13));
        $entityManager->persist(new LocalCombatant($combat, actorId: (int)$npcActor->getId(), maxHp: 17));
        $entityManager->flush();

        $application = new Application(self::$kernel);
        $tester      = new CommandTester($application->find('game:local:list-actors'));

        $exitCode = $tester->execute(['--session' => (int)$session->getId()]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $out = $tester->getDisplay();
        self::assertStringContainsString('Local session #' . $session->getId(), $out);
        self::assertStringContainsString('actor #' . $playerActor->getId(), strtolower($out));
        self::assertStringContainsString('meter=12', $out);
        self::assertStringContainsString('hp=13/13', $out);
        self::assertStringContainsString('meter=34', $out);
        self::assertStringContainsString('hp=17/17', $out);
    }
}

