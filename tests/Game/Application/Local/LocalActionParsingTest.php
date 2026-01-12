<?php

namespace App\Tests\Game\Application\Local;

use App\Entity\Character;
use App\Entity\LocalActor;
use App\Entity\World;
use App\Game\Application\Local\EnterLocalModeHandler;
use App\Game\Domain\Race;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class LocalActionParsingTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    /**
     * @return array{sessionId:int,targetActorId:int}
     */
    private function createSessionWithNpcTarget(EntityManagerInterface $entityManager): array
    {
        $world  = new World('seed-1');
        $player = new Character($world, 'Goku', Race::Saiyan);
        $npc    = new Character($world, 'Krillin', Race::Human);
        $entityManager->persist($world);
        $entityManager->persist($player);
        $entityManager->persist($npc);
        $entityManager->flush();

        $session = (new EnterLocalModeHandler($entityManager))->enter((int)$player->getId(), 3, 3);

        $playerActor = $entityManager->getRepository(LocalActor::class)->findOneBy([
            'session' => $session,
            'role'    => 'player',
        ]);
        self::assertInstanceOf(LocalActor::class, $playerActor);

        $npcActor = new LocalActor($session, characterId: (int)$npc->getId(), role: 'npc', x: $playerActor->getX(), y: $playerActor->getY());
        $entityManager->persist($npcActor);
        $entityManager->flush();

        return [
            'sessionId'     => (int)$session->getId(),
            'targetActorId' => (int)$npcActor->getId(),
        ];
    }

    public function testTalkRequiresTarget(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $session = $this->createSessionWithNpcTarget($entityManager);

        $application = new Application(self::$kernel);
        $tester      = new CommandTester($application->find('game:local:action'));

        $exitCode = $tester->execute([
            '--session' => $session['sessionId'],
            '--type'    => 'talk',
        ]);

        self::assertSame(Command::INVALID, $exitCode);
    }

    public function testTalkIsAcceptedWhenTargetProvided(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $session = $this->createSessionWithNpcTarget($entityManager);

        $application = new Application(self::$kernel);
        $tester      = new CommandTester($application->find('game:local:action'));

        $exitCode = $tester->execute([
            '--session' => $session['sessionId'],
            '--type'    => 'talk',
            '--target'  => $session['targetActorId'],
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
    }

    public function testAttackRequiresTarget(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $session = $this->createSessionWithNpcTarget($entityManager);

        $application = new Application(self::$kernel);
        $tester      = new CommandTester($application->find('game:local:action'));

        $exitCode = $tester->execute([
            '--session' => $session['sessionId'],
            '--type'    => 'attack',
        ]);

        self::assertSame(Command::INVALID, $exitCode);
    }

    public function testAttackIsAcceptedWhenTargetProvided(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $session = $this->createSessionWithNpcTarget($entityManager);

        $application = new Application(self::$kernel);
        $tester      = new CommandTester($application->find('game:local:action'));

        $exitCode = $tester->execute([
            '--session' => $session['sessionId'],
            '--type'    => 'attack',
            '--target'  => $session['targetActorId'],
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
    }
}
