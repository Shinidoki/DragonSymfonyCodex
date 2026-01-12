<?php

namespace App\Tests\Game\Http;

use App\Entity\Character;
use App\Entity\World;
use App\Entity\WorldMapTile;
use App\Game\Domain\Map\Biome;
use App\Game\Domain\Race;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WorldApiTest extends WebTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

        $tool = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testWorldShowReturnsJson(): void
    {
        $client = self::createClient();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world = new World('seed-1');
        $entityManager->persist($world);
        $entityManager->flush();

        $client->request('GET', '/api/worlds/' . $world->getId());

        self::assertResponseIsSuccessful();
        self::assertTrue(str_contains((string)$client->getResponse()->headers->get('content-type'), 'application/json'));

        $data = json_decode((string)$client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($world->getId(), $data['id']);
        self::assertSame('seed-1', $data['seed']);
        self::assertSame('Earth', $data['planetName']);
        self::assertArrayHasKey('width', $data);
        self::assertArrayHasKey('height', $data);
        self::assertArrayHasKey('currentDay', $data);
        self::assertArrayHasKey('createdAt', $data);
    }

    public function testCharacterShowReturnsJson(): void
    {
        $client = self::createClient();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world     = new World('seed-2');
        $character = new Character($world, 'Goku', Race::Saiyan);

        $entityManager->persist($world);
        $entityManager->persist($character);
        $entityManager->flush();

        $client->request('GET', '/api/characters/' . $character->getId());

        self::assertResponseIsSuccessful();

        $data = json_decode((string)$client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($character->getId(), $data['id']);
        self::assertSame($world->getId(), $data['worldId']);
        self::assertSame('Goku', $data['name']);
        self::assertSame(Race::Saiyan->value, $data['race']);
        self::assertArrayHasKey('tileX', $data);
        self::assertArrayHasKey('tileY', $data);
        self::assertArrayHasKey('targetTileX', $data);
        self::assertArrayHasKey('targetTileY', $data);
        self::assertArrayHasKey('strength', $data);
        self::assertArrayHasKey('createdAt', $data);
    }

    public function testWorldTileQueryReturnsTileJson(): void
    {
        $client = self::createClient();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world = new World('seed-3');
        $tile  = new WorldMapTile($world, 0, 0, Biome::Plains);

        $entityManager->persist($world);
        $entityManager->persist($tile);
        $entityManager->flush();

        $client->request('GET', '/api/worlds/' . $world->getId() . '/tiles?x=0&y=0');

        self::assertResponseIsSuccessful();
        $data = json_decode((string)$client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame($world->getId(), $data['worldId']);
        self::assertSame(0, $data['x']);
        self::assertSame(0, $data['y']);
        self::assertSame(Biome::Plains->value, $data['biome']);
        self::assertSame(false, $data['hasSettlement']);
    }
}
