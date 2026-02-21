<?php

declare(strict_types=1);

namespace App\Tests\Game\Application\Simulation;

use App\Entity\SimulationDailyKpi;
use App\Entity\World;
use App\Game\Application\Simulation\SimulationBalancingCatalogProviderInterface;
use App\Game\Application\Simulation\SimulationBenchmarkRunner;
use App\Game\Domain\Simulation\Balancing\SimulationBalancingCatalog;
use App\Repository\SimulationDailyKpiRepository;
use PHPUnit\Framework\TestCase;

final class SimulationBenchmarkRunnerTest extends TestCase
{
    public function testReturnsSuccessWhenAllThresholdsPass(): void
    {
        $world = new World('seed');

        $repo = $this->createMock(SimulationDailyKpiRepository::class);
        $repo->method('findByWorldDayRange')->willReturn([
            $this->kpi($world, 1, 0.2),
            $this->kpi($world, 2, 0.25),
        ]);

        $catalogProvider = $this->createMock(SimulationBalancingCatalogProviderInterface::class);
        $catalogProvider->method('get')->willReturn(new SimulationBalancingCatalog([
            'default' => ['unemployment_rate' => ['max' => 0.35]],
        ]));

        $runner = new SimulationBenchmarkRunner($repo, $catalogProvider);
        $result = $runner->run($world, 2, 'default');

        self::assertSame([], $result['violations']);
        self::assertSame(true, $result['passed']);
    }

    public function testReturnsViolationWhenThresholdExceeded(): void
    {
        $world = new World('seed');

        $repo = $this->createMock(SimulationDailyKpiRepository::class);
        $repo->method('findByWorldDayRange')->willReturn([
            $this->kpi($world, 1, 0.5),
        ]);

        $catalogProvider = $this->createMock(SimulationBalancingCatalogProviderInterface::class);
        $catalogProvider->method('get')->willReturn(new SimulationBalancingCatalog([
            'default' => ['unemployment_rate' => ['max' => 0.35]],
        ]));

        $runner = new SimulationBenchmarkRunner($repo, $catalogProvider);
        $result = $runner->run($world, 1, 'default');

        self::assertFalse($result['passed']);
        self::assertCount(1, $result['violations']);
        self::assertSame('unemployment_rate', $result['violations'][0]['metric']);
    }

    private function kpi(World $world, int $day, float $unemploymentRate): SimulationDailyKpi
    {
        return new SimulationDailyKpi($world, $day, 1, 10, 2, $unemploymentRate, 0, 0, 0, 0, 50.0, 100.0);
    }
}
