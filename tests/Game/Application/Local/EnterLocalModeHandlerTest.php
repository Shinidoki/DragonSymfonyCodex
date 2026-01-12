<?php

namespace App\Tests\Game\Application\Local;

use App\Entity\Character;
use App\Entity\LocalActor;
use App\Entity\LocalSession;
use App\Entity\World;
use App\Game\Application\Local\EnterLocalModeHandler;
use App\Game\Domain\Race;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class EnterLocalModeHandlerTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testEnterCreatesActiveSessionAtCharacterTile(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world     = new World('seed-1');
        $character = new Character($world, 'Goku', Race::Saiyan);
        $character->setTilePosition(2, 3);

        $entityManager->persist($world);
        $entityManager->persist($character);
        $entityManager->flush();

        $handler = new EnterLocalModeHandler($entityManager);

        $session = $handler->enter((int)$character->getId(), 8, 8);

        self::assertInstanceOf(LocalSession::class, $session);
        self::assertSame((int)$world->getId(), $session->getWorldId());
        self::assertSame((int)$character->getId(), $session->getCharacterId());
        self::assertSame(2, $session->getTileX());
        self::assertSame(3, $session->getTileY());
        self::assertSame('active', $session->getStatus());

        $actor = $entityManager->getRepository(LocalActor::class)->findOneBy([
            'session'     => $session,
            'characterId' => (int)$character->getId(),
        ]);

        self::assertInstanceOf(LocalActor::class, $actor);
        self::assertSame('player', $actor->getRole());
        self::assertSame($session->getPlayerX(), $actor->getX());
        self::assertSame($session->getPlayerY(), $actor->getY());

        $sessionAgain = $handler->enter((int)$character->getId(), 8, 8);
        self::assertSame((int)$session->getId(), (int)$sessionAgain->getId());

        $actorCount = $entityManager->getRepository(LocalActor::class)->count([
            'session'     => $session,
            'characterId' => (int)$character->getId(),
        ]);
        self::assertSame(1, $actorCount);
    }
}
