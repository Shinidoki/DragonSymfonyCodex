<?php

declare(strict_types=1);

namespace App\Tests\Game\Integration;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\CharacterGoal;
use App\Entity\Settlement;
use App\Entity\World;
use App\Entity\WorldMapTile;
use App\Game\Application\Simulation\AdvanceDayHandler;
use App\Game\Domain\Map\Biome;
use App\Game\Domain\Race;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TournamentDemandFeedbackLoopTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testAdaptiveFeedbackAdjustsTournamentSpendAndRadiusBySettlementHistory(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world = new World('seed-demand-loop');
        $world->setMapSize(10, 10);
        $world->advanceDays(2);
        $entityManager->persist($world);

        $strongTile = new WorldMapTile($world, 2, 2, Biome::City);
        $strongTile->setHasSettlement(true);
        $weakTile = new WorldMapTile($world, 7, 7, Biome::City);
        $weakTile->setHasSettlement(true);

        $strongSettlement = new Settlement($world, 2, 2);
        $strongSettlement->addToTreasury(5_000);
        $weakSettlement = new Settlement($world, 7, 7);
        $weakSettlement->addToTreasury(5_000);

        $strongMayor = new Character($world, 'Strong Mayor', Race::Human);
        $strongMayor->setEmployment('mayor', 2, 2);
        $strongMayor->setTilePosition(2, 2);
        $strongGoal = new CharacterGoal($strongMayor);
        $strongGoal->setLifeGoalCode('civilian.organize_events');
        $strongGoal->setCurrentGoalCode('goal.organize_tournament');
        $strongGoal->setCurrentGoalData(['spend' => 200]);
        $strongGoal->setCurrentGoalComplete(false);

        $weakMayor = new Character($world, 'Weak Mayor', Race::Human);
        $weakMayor->setEmployment('mayor', 7, 7);
        $weakMayor->setTilePosition(7, 7);
        $weakGoal = new CharacterGoal($weakMayor);
        $weakGoal->setLifeGoalCode('civilian.organize_events');
        $weakGoal->setCurrentGoalCode('goal.organize_tournament');
        $weakGoal->setCurrentGoalData(['spend' => 200]);
        $weakGoal->setCurrentGoalComplete(false);

        // Positive history for strong settlement.
        $entityManager->persist(new CharacterEvent($world, null, 'tournament_resolved', 1, [
            'center_x' => 2,
            'center_y' => 2,
            'participant_count' => 4,
            'registered_count' => 4,
            'outcome' => 'resolved',
        ]));
        $entityManager->persist(new CharacterEvent($world, null, 'tournament_resolved', 2, [
            'center_x' => 2,
            'center_y' => 2,
            'participant_count' => 5,
            'registered_count' => 5,
            'outcome' => 'resolved',
        ]));

        // Negative history for weak settlement.
        $entityManager->persist(new CharacterEvent($world, null, 'tournament_canceled', 1, [
            'center_x' => 7,
            'center_y' => 7,
            'participant_count' => 0,
            'registered_count' => 0,
            'outcome' => 'canceled',
            'reason' => 'insufficient_participants',
        ]));
        $entityManager->persist(new CharacterEvent($world, null, 'tournament_canceled', 2, [
            'center_x' => 7,
            'center_y' => 7,
            'participant_count' => 1,
            'registered_count' => 1,
            'outcome' => 'canceled',
            'reason' => 'insufficient_participants',
        ]));

        $entityManager->persist($strongTile);
        $entityManager->persist($weakTile);
        $entityManager->persist($strongSettlement);
        $entityManager->persist($weakSettlement);
        $entityManager->persist($strongMayor);
        $entityManager->persist($weakMayor);
        $entityManager->persist($strongGoal);
        $entityManager->persist($weakGoal);
        $entityManager->flush();

        $handler = self::getContainer()->get(AdvanceDayHandler::class);
        self::assertInstanceOf(AdvanceDayHandler::class, $handler);

        $handler->advance((int) $world->getId(), 1);

        $announcements = $entityManager->getRepository(CharacterEvent::class)->findBy([
            'world' => $world,
            'type' => 'tournament_announced',
            'day' => 3,
        ]);

        self::assertCount(2, $announcements);

        $eventByCoord = [];
        foreach ($announcements as $announcement) {
            $payload = $announcement->getData();
            self::assertIsArray($payload);
            $key = sprintf('%d:%d', $payload['center_x'], $payload['center_y']);
            $eventByCoord[$key] = $payload;
        }

        self::assertArrayHasKey('2:2', $eventByCoord);
        self::assertArrayHasKey('7:7', $eventByCoord);

        self::assertSame(240, $eventByCoord['2:2']['spend']);
        self::assertSame(8, $eventByCoord['2:2']['radius']);

        self::assertSame(160, $eventByCoord['7:7']['spend']);
        self::assertSame(3, $eventByCoord['7:7']['radius']);

        self::assertLessThan($eventByCoord['2:2']['spend'], $eventByCoord['7:7']['spend']);
    }
}
