<?php

namespace App\Tests\Game\Integration;

use App\Entity\CharacterEvent;
use App\Entity\Settlement;
use App\Entity\SettlementBuilding;
use App\Entity\SettlementProject;
use App\Entity\World;
use App\Game\Application\Settlement\ProjectCatalogProviderInterface;
use App\Game\Application\Settlement\SettlementProjectLifecycleService;
use App\Game\Domain\Settlement\ProjectCatalogLoader;
use App\Repository\SettlementBuildingRepository;
use App\Repository\SettlementProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SettlementProjectStartEventLoopTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testMaterializesSettlementProjectAndChargesMaterialsCost(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world = new World('seed-1');
        $world->advanceDays(1); // day = 1
        $entityManager->persist($world);

        $settlement = new Settlement($world, 5, 5);
        $settlement->addToTreasury(1_000);
        $entityManager->persist($settlement);

        $event = new CharacterEvent(
            world: $world,
            character: null,
            type: 'settlement_project_start_requested',
            day: 1,
            data: [
                'settlement_x'  => 5,
                'settlement_y'  => 5,
                'building_code' => 'dojo',
                'target_level'  => 1,
            ],
        );
        $entityManager->persist($event);
        $entityManager->flush();

        /** @var SettlementProjectRepository $projects */
        $projects = $entityManager->getRepository(SettlementProject::class);
        /** @var SettlementBuildingRepository $buildings */
        $buildings = $entityManager->getRepository(SettlementBuilding::class);

        $projectDir      = dirname(__DIR__, 3);
        $catalogProvider = new class($projectDir) implements ProjectCatalogProviderInterface {
            public function __construct(private readonly string $projectDir)
            {
            }

            public function get(): \App\Game\Domain\Settlement\ProjectCatalog
            {
                $loader = new ProjectCatalogLoader();

                return $loader->loadFromFile($this->projectDir . '/config/game/projects.yaml');
            }
        };

        $service = new SettlementProjectLifecycleService(
            entityManager: $entityManager,
            projects: $projects,
            buildings: $buildings,
            catalogProvider: $catalogProvider,
        );

        $service->advanceDay($world, worldDay: 1, settlements: [$settlement], emittedEvents: [$event]);

        $entityManager->refresh($settlement);
        self::assertSame(200, $settlement->getTreasury(), 'Dojo L1 materials_cost=800 should be deducted.');

        $project = $projects->findActiveForSettlement($settlement);
        self::assertNotNull($project);
        self::assertSame('dojo', $project->getBuildingCode());
        self::assertSame(1, $project->getTargetLevel());
        self::assertSame((int)$event->getId(), $project->getRequestEventId());
    }
}
