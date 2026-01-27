<?php

namespace App\Tests\Game\Integration;

use App\Entity\Character;
use App\Entity\NpcProfile;
use App\Entity\Settlement;
use App\Entity\SettlementBuilding;
use App\Entity\World;
use App\Entity\WorldMapTile;
use App\Game\Application\Economy\EconomyCatalogProviderInterface;
use App\Game\Application\Settlement\ProjectCatalogProviderInterface;
use App\Game\Application\Simulation\AdvanceDayHandler;
use App\Game\Domain\Map\Biome;
use App\Game\Domain\Npc\NpcArchetype;
use App\Game\Domain\Race;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DojoTrainingPaymentTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testTrainingInMasteredDojoPaysMasterAndTaxesToSettlement(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $economyCatalog = self::getContainer()->get(EconomyCatalogProviderInterface::class)->get();
        $projectCatalog = self::getContainer()->get(ProjectCatalogProviderInterface::class)->get();

        $world = new World('seed-1');
        $world->setMapSize(8, 8);
        $entityManager->persist($world);

        $tile = new WorldMapTile($world, 3, 0, Biome::City);
        $tile->setHasSettlement(true);
        $tile->setHasDojo(true);
        $entityManager->persist($tile);

        $settlement = new Settlement($world, 3, 0);
        $settlement->addToTreasury(0);
        $entityManager->persist($settlement);

        $master = new Character($world, 'Master', Race::Human);
        $master->setTilePosition(3, 0);
        $master->addMoney(0);
        $entityManager->persist($master);

        $masterProfile = new NpcProfile($master, NpcArchetype::Civilian);
        $entityManager->persist($masterProfile);

        $trainee = new Character($world, 'Trainee', Race::Human);
        $trainee->setTilePosition(3, 0);
        $trainee->addMoney(1_000);
        $entityManager->persist($trainee);

        $dojo = new SettlementBuilding($settlement, 'dojo', 1);
        $dojo->setMasterCharacter($master);
        $entityManager->persist($dojo);

        $entityManager->flush();

        $fee = $projectCatalog->dojoTrainingFee(1);
        self::assertGreaterThan(0, $fee);

        $tax = (int)floor($fee * $economyCatalog->settlementTaxRate());
        $net = $fee - $tax;

        $handler = self::getContainer()->get(AdvanceDayHandler::class);
        self::assertInstanceOf(AdvanceDayHandler::class, $handler);

        $handler->advance((int)$world->getId(), 1);

        $entityManager->refresh($settlement);
        $entityManager->refresh($master);
        $entityManager->refresh($trainee);

        self::assertSame($net, $master->getMoney());
        self::assertSame(1_000 - $fee, $trainee->getMoney());
        self::assertSame($tax, $settlement->getTreasury());
    }
}

