<?php

namespace App\Tests\Game\Http;

use App\Entity\Character;
use App\Entity\LocalActor;
use App\Entity\World;
use App\Game\Application\Local\EnterLocalModeHandler;
use App\Game\Domain\Race;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LocalSessionApiTest extends WebTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testLocalSessionShowReturnsActors(): void
    {
        $client = self::createClient();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world  = new World('seed-1');
        $player = new Character($world, 'Goku', Race::Saiyan);
        $npc    = new Character($world, 'Krillin', Race::Human);

        $entityManager->persist($world);
        $entityManager->persist($player);
        $entityManager->persist($npc);
        $entityManager->flush();

        $enter   = new EnterLocalModeHandler($entityManager);
        $session = $enter->enter((int)$player->getId(), 8, 8);

        $npcActor = new LocalActor($session, characterId: (int)$npc->getId(), role: 'npc', x: 0, y: 0);
        $entityManager->persist($npcActor);
        $entityManager->flush();

        $client->request('GET', '/api/local-sessions/' . $session->getId());

        self::assertResponseIsSuccessful();

        $data = json_decode((string)$client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($session->getId(), $data['id']);
        self::assertSame($session->getWorldId(), $data['worldId']);
        self::assertSame($session->getCharacterId(), $data['characterId']);
        self::assertSame($session->getCurrentTick(), $data['currentTick']);
        self::assertSame($session->getStatus(), $data['status']);

        self::assertIsArray($data['actors']);
        self::assertGreaterThanOrEqual(2, count($data['actors']));
    }
}

