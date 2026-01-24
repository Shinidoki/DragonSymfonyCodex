<?php

namespace App\Command;

use App\Game\Application\Simulation\AdvanceDayHandler;
use App\Game\Domain\Stats\StatTier;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'game:sim:advance',
    description: 'Advance the simulation for a world by N days (MVP: every character trains once per day).',
)]
final class GameSimAdvanceCommand extends Command
{
    public function __construct(private readonly AdvanceDayHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('world', null, InputOption::VALUE_REQUIRED, 'World id');
        $this->addOption('days', null, InputOption::VALUE_OPTIONAL, 'Number of days to advance', 1);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $worldId = (int)$input->getOption('world');
        $days    = (int)$input->getOption('days');

        if ($worldId <= 0) {
            $output->writeln('<error>--world must be a positive integer</error>');
            return Command::INVALID;
        }

        if ($days <= 0) {
            $output->writeln('<error>--days must be a positive integer</error>');
            return Command::INVALID;
        }

        $result = $this->handler->advance($worldId, $days);

        $output->writeln(sprintf('World #%d advanced by %d day(s) to day %d.', (int)$result->world->getId(), $result->daysAdvanced, $result->world->getCurrentDay()));

        foreach ($result->characters as $character) {
            $job = $character->isEmployed()
                ? sprintf(
                    '%s @ (%d,%d)',
                    (string)$character->getEmploymentJobCode(),
                    (int)$character->getEmploymentSettlementX(),
                    (int)$character->getEmploymentSettlementY(),
                )
                : 'unemployed';

            $output->writeln(sprintf(
                '- %s: $%d, job %s, STR %s (%d), KI-CONTROL %s (%d)',
                $character->getName(),
                $character->getMoney(),
                $job,
                StatTier::fromValue($character->getStrength())->label(),
                $character->getStrength(),
                StatTier::fromValue($character->getKiControl())->label(),
                $character->getKiControl(),
            ));
        }

        return Command::SUCCESS;
    }
}
