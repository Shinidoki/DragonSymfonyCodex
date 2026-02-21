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

final class SettlementMigrationPressureLoopTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testMigrationCommitEventDrivesFindJobGoalAndCooldownPreventsImmediateRepeat(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world = new World('seed-migration-loop');
        $world->setMapSize(12, 12);

        $sourceTile = new WorldMapTile($world, 1, 1, Biome::City);
        $sourceTile->setHasSettlement(true);

        $targetTile = new WorldMapTile($world, 8, 1, Biome::City);
        $targetTile->setHasSettlement(true);

        $source = new Settlement($world, 1, 1);
        $source->setProsperity(10);
        $source->addToTreasury(10);

        $target = new Settlement($world, 8, 1);
        $target->setProsperity(95);
        $target->addToTreasury(2_500);

        $migrant = new Character($world, 'Migrant', Race::Human);
        $migrant->setTilePosition(1, 1);

        $mayor = new Character($world, 'Mayor', Race::Human);
        $mayor->setTilePosition(1, 1);
        $mayor->setEmployment('mayor', 1, 1);

        $goal = new CharacterGoal($migrant);
        $goal->setLifeGoalCode('civilian.have_family');
        $goal->setCurrentGoalCode('goal.earn_money');
        $goal->setCurrentGoalData(['target_amount' => 100]);
        $goal->setCurrentGoalComplete(false);
        $goal->setLastResolvedDay(0);

        $entityManager->persist($world);
        $entityManager->persist($sourceTile);
        $entityManager->persist($targetTile);
        $entityManager->persist($source);
        $entityManager->persist($target);
        $entityManager->persist($migrant);
        $entityManager->persist($mayor);
        $entityManager->persist($goal);
        $entityManager->flush();

        $handler = self::getContainer()->get(AdvanceDayHandler::class);
        self::assertInstanceOf(AdvanceDayHandler::class, $handler);

        $handler->advance((int) $world->getId(), 2);

        $entityManager->refresh($goal);

        $commitDay1 = $entityManager->getRepository(CharacterEvent::class)->findOneBy([
            'world' => $world,
            'character' => $migrant,
            'type' => 'settlement_migration_committed',
            'day' => 1,
        ]);
        self::assertInstanceOf(CharacterEvent::class, $commitDay1);
        self::assertSame(1, $commitDay1->getData()['from_x'] ?? null);
        self::assertSame(1, $commitDay1->getData()['from_y'] ?? null);
        self::assertSame(8, $commitDay1->getData()['target_x'] ?? null);
        self::assertSame(1, $commitDay1->getData()['target_y'] ?? null);

        $commitDay2 = $entityManager->getRepository(CharacterEvent::class)->findOneBy([
            'world' => $world,
            'character' => $migrant,
            'type' => 'settlement_migration_committed',
            'day' => 2,
        ]);
        self::assertNull($commitDay2);

        self::assertSame('goal.find_job', $goal->getCurrentGoalCode());
        self::assertSame(8, $goal->getCurrentGoalData()['target_x'] ?? null);
        self::assertSame(1, $goal->getCurrentGoalData()['target_y'] ?? null);
    }
}
