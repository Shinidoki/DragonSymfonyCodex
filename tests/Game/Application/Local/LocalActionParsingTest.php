<?php

namespace App\Tests\Game\Application\Local;

use App\Entity\Character;
use App\Entity\LocalActor;
use App\Entity\TechniqueDefinition;
use App\Entity\World;
use App\Game\Application\Local\EnterLocalModeHandler;
use App\Game\Domain\Race;
use App\Game\Domain\Techniques\TechniqueType;
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

    public function testTechniqueIsAcceptedWithDirectionAim(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $session = $this->createSessionWithNpcTarget($entityManager);

        $application = new Application(self::$kernel);
        $tester      = new CommandTester($application->find('game:local:action'));

        $exitCode = $tester->execute([
            '--session'   => $session['sessionId'],
            '--type'      => 'technique',
            '--technique' => 'ki_blast',
            '--dir'       => 'north',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
    }

    public function testTechniqueIsAcceptedWithPointAim(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $session = $this->createSessionWithNpcTarget($entityManager);

        $application = new Application(self::$kernel);
        $tester      = new CommandTester($application->find('game:local:action'));

        $exitCode = $tester->execute([
            '--session'   => $session['sessionId'],
            '--type'      => 'technique',
            '--technique' => 'ki_blast',
            '--x'         => 1,
            '--y'         => 2,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
    }

    public function testTechniqueIsAcceptedWithTargetAim(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $session = $this->createSessionWithNpcTarget($entityManager);

        $application = new Application(self::$kernel);
        $tester      = new CommandTester($application->find('game:local:action'));

        $exitCode = $tester->execute([
            '--session'   => $session['sessionId'],
            '--type'      => 'technique',
            '--technique' => 'ki_blast',
            '--target'    => $session['targetActorId'],
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
    }

    public function testTechniqueRejectsMultipleAimOptions(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $session = $this->createSessionWithNpcTarget($entityManager);

        $application = new Application(self::$kernel);
        $tester      = new CommandTester($application->find('game:local:action'));

        $exitCode = $tester->execute([
            '--session'   => $session['sessionId'],
            '--type'      => 'technique',
            '--technique' => 'ki_blast',
            '--dir'       => 'north',
            '--target'    => $session['targetActorId'],
        ]);

        self::assertSame(Command::INVALID, $exitCode);
    }

    public function testTechniqueRejectsIncompletePointAim(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $session = $this->createSessionWithNpcTarget($entityManager);

        $application = new Application(self::$kernel);
        $tester      = new CommandTester($application->find('game:local:action'));

        $exitCode = $tester->execute([
            '--session'   => $session['sessionId'],
            '--type'      => 'technique',
            '--technique' => 'ki_blast',
            '--x'         => 1,
        ]);

        self::assertSame(Command::INVALID, $exitCode);
    }

    public function testTechniqueWithoutAimIsRejectedWhenTechniqueDoesNotSupportSelf(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $session = $this->createSessionWithNpcTarget($entityManager);

        $entityManager->persist(new TechniqueDefinition(
            code: 'ki_blast',
            name: 'Ki Blast',
            type: TechniqueType::Blast,
            config: [
                'aimModes' => ['actor', 'dir', 'point'],
                'delivery' => 'projectile',
                'range'    => 2,
                'kiCost'   => 3,
                'piercing' => 'first',
            ],
            enabled: true,
            version: 1,
        ));
        $entityManager->flush();

        $application = new Application(self::$kernel);
        $tester      = new CommandTester($application->find('game:local:action'));

        $exitCode = $tester->execute([
            '--session'   => $session['sessionId'],
            '--type'      => 'technique',
            '--technique' => 'ki_blast',
        ]);

        self::assertSame(Command::INVALID, $exitCode);
    }

    public function testTechniqueWithoutAimIsAcceptedWhenTechniqueSupportsSelf(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $session = $this->createSessionWithNpcTarget($entityManager);

        $entityManager->persist(new TechniqueDefinition(
            code: 'self_burst',
            name: 'Self Burst',
            type: TechniqueType::Blast,
            config: [
                'aimModes'  => ['self'],
                'delivery'  => 'aoe',
                'range'     => 0,
                'kiCost'    => 0,
                'aoeRadius' => 1,
            ],
            enabled: true,
            version: 1,
        ));
        $entityManager->flush();

        $application = new Application(self::$kernel);
        $tester      = new CommandTester($application->find('game:local:action'));

        $exitCode = $tester->execute([
            '--session'   => $session['sessionId'],
            '--type'      => 'technique',
            '--technique' => 'self_burst',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
    }

    public function testChargedTechniqueWithoutAimIsAcceptedEvenWhenItDoesNotSupportSelf(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $session = $this->createSessionWithNpcTarget($entityManager);

        $entityManager->persist(new TechniqueDefinition(
            code: 'kamehameha',
            name: 'Kamehameha',
            type: TechniqueType::Charged,
            config: [
                'aimModes'    => ['actor', 'dir', 'point'],
                'delivery'    => 'ray',
                'piercing'    => 'all',
                'range'       => 4,
                'kiCost'      => 12,
                'chargeTicks' => 2,
            ],
            enabled: true,
            version: 1,
        ));
        $entityManager->flush();

        $application = new Application(self::$kernel);
        $tester      = new CommandTester($application->find('game:local:action'));

        $exitCode = $tester->execute([
            '--session'   => $session['sessionId'],
            '--type'      => 'technique',
            '--technique' => 'kamehameha',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
    }
}
