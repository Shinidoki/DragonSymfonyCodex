<?php

namespace App\Tests\Game\Application\Simulation;

use App\Entity\Character;
use App\Entity\Settlement;
use App\Entity\SettlementBuilding;
use App\Entity\SettlementProject;
use App\Entity\World;
use App\Game\Application\Settlement\ProjectCatalogProviderInterface;
use App\Game\Application\Simulation\SettlementSimulationContextBuilder;
use App\Game\Domain\Map\TileCoord;
use App\Game\Domain\Race;
use App\Game\Domain\Settlement\ProjectCatalogLoader;
use App\Repository\SettlementBuildingRepository;
use App\Repository\SettlementProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SettlementSimulationContextBuilderTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testBuildsBuildingsProjectsAndDojoMultipliers(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world = new World('seed-1');
        $entityManager->persist($world);

        $a = new Settlement($world, 0, 0);
        $b = new Settlement($world, 1, 1);
        $entityManager->persist($a);
        $entityManager->persist($b);

        $master = new Character($world, 'Master', Race::Human);
        $entityManager->persist($master);

        $bDojo = new SettlementBuilding($b, 'dojo', 2);
        $bDojo->setMasterCharacter($master);
        $entityManager->persist($bDojo);

        $bProject = new SettlementProject($b, 'dojo', 3, 100, 0, null);
        $entityManager->persist($bProject);

        $entityManager->flush();

        /** @var SettlementProjectRepository $projects */
        $projects = $entityManager->getRepository(SettlementProject::class);
        /** @var SettlementBuildingRepository $buildings */
        $buildings = $entityManager->getRepository(SettlementBuilding::class);

        $projectDir      = dirname(__DIR__, 4);
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

        $builder = new SettlementSimulationContextBuilder(
            settlementProjects: $projects,
            settlementBuildings: $buildings,
            projectCatalogProvider: $catalogProvider,
        );

        [$buildingsByCoord, $projectsByCoord, $dojoMultipliersByCoord] = array_slice($builder->build(
            dojoCoords: [new TileCoord(0, 0)],
            settlements: [$a, $b],
        ), 0, 3);

        self::assertSame(1, $buildingsByCoord['0:0']['dojo']);
        self::assertSame(2, $buildingsByCoord['1:1']['dojo']);

        self::assertSame(['building_code' => 'dojo', 'target_level' => 3], $projectsByCoord['1:1']);

        self::assertArrayNotHasKey('0:0', $dojoMultipliersByCoord, 'Unowned dojos should not provide an XP multiplier.');
        self::assertEquals(1.35, $dojoMultipliersByCoord['1:1']);
    }
}
