<?php

namespace App\Tests\Game\Integration;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\Settlement;
use App\Entity\SettlementBuilding;
use App\Entity\World;
use App\Entity\WorldMapTile;
use App\Game\Application\Dojo\DojoLifecycleService;
use App\Game\Domain\Map\Biome;
use App\Game\Domain\Race;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DojoBuildingUniquenessTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testDoesNotCreateDuplicateDojoBuildingWhenMultipleClaimEventsOccurSameDay(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world = new World('seed-1');
        $world->setMapSize(8, 8);
        $entityManager->persist($world);

        $tile = new WorldMapTile($world, 3, 0, Biome::City);
        $tile->setHasSettlement(true);
        $tile->setHasDojo(true);
        $entityManager->persist($tile);

        $settlement = new Settlement($world, 3, 0);
        $entityManager->persist($settlement);

        $claimant = new Character($world, 'Claimant', Race::Human);
        $claimant->setTilePosition(3, 0);
        $entityManager->persist($claimant);

        $entityManager->flush();

        $e1 = new CharacterEvent($world, $claimant, 'dojo_claim_requested', 1, ['settlement_x' => 3, 'settlement_y' => 0]);
        $e2 = new CharacterEvent($world, $claimant, 'dojo_claim_requested', 1, ['settlement_x' => 3, 'settlement_y' => 0]);

        $service = self::getContainer()->get(DojoLifecycleService::class);
        self::assertInstanceOf(DojoLifecycleService::class, $service);

        $service->advanceDay(
            world: $world,
            worldDay: 1,
            settlements: [$settlement],
            characters: [$claimant],
            emittedEvents: [$e1, $e2],
        );

        try {
            $entityManager->flush();
        } catch (UniqueConstraintViolationException $e) {
            self::fail('Duplicate dojo building created for the same settlement and code.');
        }

        $count = $entityManager->getRepository(SettlementBuilding::class)->count([
            'settlement' => $settlement,
            'code'       => 'dojo',
        ]);
        self::assertSame(1, $count);
    }
}

