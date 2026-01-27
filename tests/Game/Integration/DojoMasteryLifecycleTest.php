<?php

namespace App\Tests\Game\Integration;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\Settlement;
use App\Entity\SettlementBuilding;
use App\Entity\World;
use App\Entity\WorldMapTile;
use App\Game\Application\Dojo\DojoLifecycleService;
use App\Game\Application\Settlement\ProjectCatalogProviderInterface;
use App\Game\Domain\Map\Biome;
use App\Game\Domain\Race;
use App\Game\Domain\Stats\CoreAttributes;
use App\Repository\SettlementBuildingRepository;
use App\Repository\WorldMapTileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DojoMasteryLifecycleTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testClaimAndChallengeAndCooldown(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world = new World('seed-1');
        $world->setMapSize(8, 8);
        $world->advanceDays(1); // day = 1
        $entityManager->persist($world);

        $tile = new WorldMapTile($world, 3, 0, Biome::City);
        $tile->setHasSettlement(true);
        $tile->setHasDojo(true);
        $entityManager->persist($tile);

        $settlement = new Settlement($world, 3, 0);
        $entityManager->persist($settlement);

        $dojo = new SettlementBuilding($settlement, 'dojo', 1);
        $entityManager->persist($dojo);

        $claimant = new Character($world, 'Claimant', Race::Human);
        $claimant->setTilePosition(3, 0);
        $entityManager->persist($claimant);

        $entityManager->flush();

        /** @var SettlementBuildingRepository $buildings */
        $buildings = $entityManager->getRepository(SettlementBuilding::class);
        /** @var WorldMapTileRepository $tiles */
        $tiles           = $entityManager->getRepository(WorldMapTile::class);
        $catalogProvider = self::getContainer()->get(ProjectCatalogProviderInterface::class);
        self::assertInstanceOf(ProjectCatalogProviderInterface::class, $catalogProvider);

        $service = new DojoLifecycleService($entityManager, $buildings, $tiles, $catalogProvider);

        $claimEvent = new CharacterEvent(
            world: $world,
            character: $claimant,
            type: 'dojo_claim_requested',
            day: 1,
            data: ['settlement_x' => 3, 'settlement_y' => 0],
        );
        $entityManager->persist($claimEvent);
        $entityManager->flush();

        $service->advanceDay($world, worldDay: 1, settlements: [$settlement], characters: [$claimant], emittedEvents: [$claimEvent]);
        $entityManager->flush();

        $entityManager->refresh($dojo);
        self::assertSame((int)$claimant->getId(), (int)$dojo->getMasterCharacter()?->getId());

        $master = $claimant;

        $challenger = new Character($world, 'Challenger', Race::Human);
        $challenger->setTilePosition(3, 0);
        $challenger->applyCoreAttributes(new CoreAttributes(
            strength: 50,
            speed: 50,
            endurance: 50,
            durability: 50,
            kiCapacity: 50,
            kiControl: 50,
            kiRecovery: 50,
            focus: 50,
            discipline: 50,
            adaptability: 50,
        ));
        $entityManager->persist($challenger);
        $entityManager->flush();

        $challengeEvent = new CharacterEvent(
            world: $world,
            character: $challenger,
            type: 'dojo_challenge_requested',
            day: 1,
            data: ['settlement_x' => 3, 'settlement_y' => 0],
        );
        $entityManager->persist($challengeEvent);
        $entityManager->flush();

        $events = $service->advanceDay(
            $world,
            worldDay: 1,
            settlements: [$settlement],
            characters: [$master, $challenger],
            emittedEvents: [$challengeEvent],
        );
        $entityManager->flush();

        $types = array_map(static fn(CharacterEvent $e): string => $e->getType(), $events);
        self::assertContains('sim_fight_resolved', $types);

        $entityManager->refresh($dojo);
        self::assertSame((int)$challenger->getId(), (int)$dojo->getMasterCharacter()?->getId());
        self::assertSame(1, $dojo->getMasterLastChallengedDay());

        // Cooldown blocks day 2 re-challenge.
        $world->advanceDays(1); // day = 2
        $entityManager->flush();

        $challenger2 = new Character($world, 'Challenger2', Race::Human);
        $challenger2->setTilePosition(3, 0);
        $challenger2->applyCoreAttributes(new CoreAttributes(
            strength: 80,
            speed: 80,
            endurance: 80,
            durability: 80,
            kiCapacity: 80,
            kiControl: 80,
            kiRecovery: 80,
            focus: 80,
            discipline: 80,
            adaptability: 80,
        ));
        $entityManager->persist($challenger2);
        $entityManager->flush();

        $challengeEvent2 = new CharacterEvent(
            world: $world,
            character: $challenger2,
            type: 'dojo_challenge_requested',
            day: 2,
            data: ['settlement_x' => 3, 'settlement_y' => 0],
        );
        $entityManager->persist($challengeEvent2);
        $entityManager->flush();

        $service->advanceDay(
            $world,
            worldDay: 2,
            settlements: [$settlement],
            characters: [$challenger, $challenger2],
            emittedEvents: [$challengeEvent2],
        );
        $entityManager->flush();

        $entityManager->refresh($dojo);
        self::assertSame((int)$challenger->getId(), (int)$dojo->getMasterCharacter()?->getId());
        self::assertSame(1, $dojo->getMasterLastChallengedDay());
    }
}
