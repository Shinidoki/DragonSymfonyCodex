<?php

namespace App\Tests\Game\Application\Local;

use App\Entity\Character;
use App\Entity\World;
use App\Game\Application\Local\EnterLocalModeHandler;
use App\Game\Application\Local\ExitLocalModeHandler;
use App\Game\Domain\Race;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ExitLocalModeHandlerTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testExitSuspendsSession(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world     = new World('seed-1');
        $character = new Character($world, 'Goku', Race::Saiyan);

        $entityManager->persist($world);
        $entityManager->persist($character);
        $entityManager->flush();

        $enter   = new EnterLocalModeHandler($entityManager);
        $session = $enter->enter((int)$character->getId(), 8, 8);

        $exit   = new ExitLocalModeHandler($entityManager);
        $exited = $exit->exit((int)$session->getId());

        self::assertSame('suspended', $exited->getStatus());
    }
}

