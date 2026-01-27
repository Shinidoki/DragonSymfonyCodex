<?php

namespace App\Tests\Game\Application\Simulation;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SimulateWorldCommandTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testCommandCreatesAndSimulatesWorldAndPrintsStats(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $application = new Application(self::$kernel);
        self::assertTrue($application->has('game:sim:simulate-world'));

        $tester = new CommandTester($application->find('game:sim:simulate-world'));
        $exit   = $tester->execute(['days' => '3']);

        self::assertSame(Command::SUCCESS, $exit);

        $out = $tester->getDisplay();
        self::assertStringContainsString('Day 3/3', $out);
        self::assertStringContainsString('Last event:', $out);
        self::assertStringContainsString('Created world', $out);
        self::assertStringContainsString('Simulated: 3 day(s)', $out);
        self::assertStringContainsString('Characters:', $out);
        self::assertStringContainsString('Strongest:', $out);
    }

    public function testRejectsNonPositiveDays(): void
    {
        self::bootKernel();

        $application = new Application(self::$kernel);
        self::assertTrue($application->has('game:sim:simulate-world'));

        $tester = new CommandTester($application->find('game:sim:simulate-world'));
        $exit   = $tester->execute(['days' => '0']);

        self::assertSame(Command::INVALID, $exit);
        self::assertStringContainsString('days must be a positive integer', $tester->getDisplay());
    }
}
