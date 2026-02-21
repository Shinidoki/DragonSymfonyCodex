<?php

declare(strict_types=1);

namespace App\Command;

use App\Game\Application\Simulation\SimulationBenchmarkRunnerInterface;
use App\Repository\WorldRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:simulation:benchmark', description: 'Run simulation KPI benchmark against configured thresholds')]
final class SimulationBenchmarkCommand extends Command
{
    public function __construct(
        private readonly WorldRepository $worldRepository,
        private readonly SimulationBenchmarkRunnerInterface $runner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('worldId', InputArgument::REQUIRED);
        $this->addOption('days', null, InputOption::VALUE_REQUIRED, 'Number of recent days to evaluate', '500');
        $this->addOption('profile', null, InputOption::VALUE_REQUIRED, 'Threshold profile', 'default');
        $this->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: table|json', 'table');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $worldId = (int) $input->getArgument('worldId');
        $days = (int) $input->getOption('days');
        $profile = (string) $input->getOption('profile');
        $format = (string) $input->getOption('format');

        $world = $this->worldRepository->find($worldId);
        if (!$world) {
            $output->writeln(sprintf('<error>World not found: %d</error>', $worldId));
            return Command::FAILURE;
        }

        $result = $this->runner->run($world, $days, $profile);

        if ($format === 'json') {
            $output->writeln((string) json_encode($result, JSON_THROW_ON_ERROR));
        } else {
            $output->writeln(sprintf('profile=%s sample=%d passed=%s', $result['profile'], $result['sample_size'] ?? 0, $result['passed'] ? 'yes' : 'no'));
            foreach ($result['violations'] as $v) {
                $output->writeln(sprintf('- %s %s observed=%s', $v['metric'], $v['kind'], (string) $v['observed']));
            }
        }

        return $result['passed'] ? Command::SUCCESS : Command::FAILURE;
    }
}
