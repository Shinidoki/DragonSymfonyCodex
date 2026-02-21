<?php

declare(strict_types=1);

namespace App\Tests\Game\Application\Simulation;

use App\Command\SimulationBenchmarkCommand;
use App\Entity\World;
use App\Game\Application\Simulation\SimulationBenchmarkRunnerInterface;
use App\Repository\WorldRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SimulationBenchmarkCommandTest extends TestCase
{
    public function testReturnsSuccessWhenBenchmarkPasses(): void
    {
        $world = new World('seed');

        $worldRepository = $this->createMock(WorldRepository::class);
        $worldRepository->method('find')->with(9)->willReturn($world);

        $runner = $this->createMock(SimulationBenchmarkRunnerInterface::class);
        $runner->method('run')->willReturn([
            'passed' => true,
            'violations' => [],
            'profile' => 'default',
            'sample_size' => 100,
        ]);

        $command = new SimulationBenchmarkCommand($worldRepository, $runner);
        $tester = new CommandTester($command);

        $exit = $tester->execute(['worldId' => '9', '--days' => '100', '--profile' => 'default', '--format' => 'json']);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('"passed":true', str_replace(' ', '', $tester->getDisplay()));
    }

    public function testReturnsFailureWhenBenchmarkFails(): void
    {
        $world = new World('seed');

        $worldRepository = $this->createMock(WorldRepository::class);
        $worldRepository->method('find')->with(9)->willReturn($world);

        $runner = $this->createMock(SimulationBenchmarkRunnerInterface::class);
        $runner->method('run')->willReturn([
            'passed' => false,
            'violations' => [['metric' => 'unemployment_rate', 'max' => 0.35, 'observed' => 0.5, 'kind' => 'max']],
            'profile' => 'default',
            'sample_size' => 100,
        ]);

        $command = new SimulationBenchmarkCommand($worldRepository, $runner);
        $tester = new CommandTester($command);

        $exit = $tester->execute(['worldId' => '9', '--days' => '100', '--profile' => 'default']);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('unemployment_rate', $tester->getDisplay());
    }
}
