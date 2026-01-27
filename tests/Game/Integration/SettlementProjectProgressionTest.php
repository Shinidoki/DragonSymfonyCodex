<?php

namespace App\Tests\Game\Integration;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\Settlement;
use App\Entity\SettlementBuilding;
use App\Entity\SettlementProject;
use App\Entity\World;
use App\Entity\WorldMapTile;
use App\Game\Application\Settlement\ProjectCatalogProviderInterface;
use App\Game\Application\Settlement\SettlementProjectLifecycleService;
use App\Game\Domain\Economy\EconomyCatalog;
use App\Game\Domain\Map\Biome;
use App\Game\Domain\Race;
use App\Game\Domain\Settlement\ProjectCatalogLoader;
use App\Repository\SettlementBuildingRepository;
use App\Repository\SettlementProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SettlementProjectProgressionTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    private function service(EntityManagerInterface $entityManager): SettlementProjectLifecycleService
    {
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

        return new SettlementProjectLifecycleService(
            entityManager: $entityManager,
            projects: $projects,
            buildings: $buildings,
            catalogProvider: $catalogProvider,
        );
    }

    private function economyCatalog(): EconomyCatalog
    {
        return new EconomyCatalog(
            jobs: [
                'laborer' => ['label' => 'Laborer', 'wage_weight' => 100, 'work_radius' => 0],
            ],
            employmentPools: [],
            settlement: [
                'wage_pool_rate' => 0.5,
                'tax_rate'       => 0.0,
                'production'     => [
                    'per_work_unit_base'            => 1,
                    'per_work_unit_prosperity_mult' => 0,
                    'randomness_pct'                => 0.0,
                ],
            ],
            thresholds: [
                'money_low_employed'   => 0,
                'money_low_unemployed' => 0,
            ],
            tournaments: [],
        );
    }

    public function testAdvancesActiveProjectAndCompletesIntoBuildingLevel(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world = new World('seed-1');
        $world->advanceDays(1); // day = 1
        $entityManager->persist($world);

        $settlement = new Settlement($world, 0, 0);
        $settlement->setProsperity(50);
        $settlement->addToTreasury(20_000);
        $entityManager->persist($settlement);

        $tile = new WorldMapTile($world, 0, 0, Biome::Plains);
        $tile->setHasSettlement(true);
        $entityManager->persist($tile);

        $event = new CharacterEvent(
            world: $world,
            character: null,
            type: 'settlement_project_start_requested',
            day: 1,
            data: [
                'settlement_x'  => 0,
                'settlement_y'  => 0,
                'building_code' => 'dojo',
                'target_level'  => 1,
            ],
        );
        $entityManager->persist($event);
        $entityManager->flush();

        $service = $this->service($entityManager);

        // Create the project.
        $service->advanceDay($world, worldDay: 1, settlements: [$settlement], emittedEvents: [$event]);

        /** @var SettlementProjectRepository $projects */
        $projects = $entityManager->getRepository(SettlementProject::class);
        $project  = $projects->findActiveForSettlement($settlement);
        self::assertNotNull($project);

        $a = new Character($world, 'A', Race::Human);
        $a->setEmployment('laborer', 0, 0);
        $a->setWorkFocus(100);
        $a->setTilePosition(0, 0);

        $b = new Character($world, 'B', Race::Human);
        $b->setEmployment('laborer', 0, 0);
        $b->setWorkFocus(100);
        $b->setTilePosition(0, 0);

        // Progress for 3 days; base_required_work_units=150, diversion_fraction=0.30, two workers @100 weight => 60/day.
        $eventsDay2 = $service->advanceDay(
            $world,
            worldDay: 2,
            settlements: [$settlement],
            emittedEvents: [],
            characters: [$a, $b],
            economyCatalog: $this->economyCatalog(),
        );
        self::assertCount(0, $eventsDay2);

        $eventsDay3 = $service->advanceDay(
            $world,
            worldDay: 3,
            settlements: [$settlement],
            emittedEvents: [],
            characters: [$a, $b],
            economyCatalog: $this->economyCatalog(),
        );
        self::assertCount(0, $eventsDay3);

        $eventsDay4 = $service->advanceDay(
            $world,
            worldDay: 4,
            settlements: [$settlement],
            emittedEvents: [],
            characters: [$a, $b],
            economyCatalog: $this->economyCatalog(),
        );

        self::assertNotEmpty($eventsDay4);
        self::assertSame('settlement_project_completed', $eventsDay4[0]->getType());

        $entityManager->refresh($settlement);
        $entityManager->refresh($project);

        self::assertSame(SettlementProject::STATUS_COMPLETED, $project->getStatus());

        /** @var SettlementBuildingRepository $buildings */
        $buildings = $entityManager->getRepository(SettlementBuilding::class);
        $dojo      = $buildings->findOneBySettlementAndCode($settlement, 'dojo');
        self::assertNotNull($dojo);
        self::assertSame(1, $dojo->getLevel());

        $entityManager->refresh($tile);
        self::assertTrue($tile->hasDojo(), 'Completing a dojo project must set hasDojo=true on the world tile.');
    }
}
