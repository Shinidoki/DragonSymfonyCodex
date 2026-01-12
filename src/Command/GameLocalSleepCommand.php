<?php

namespace App\Command;

use App\Game\Application\Local\LongActionType;
use App\Game\Application\Local\StartLongActionHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'game:local:sleep',
    description: 'Sleep for N days while in local mode (suspends local session, advances days, then resumes).',
)]
final class GameLocalSleepCommand extends Command
{
    public function __construct(private readonly StartLongActionHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('session', null, InputOption::VALUE_REQUIRED, 'Local session id');
        $this->addOption('days', null, InputOption::VALUE_OPTIONAL, 'Days to sleep', 1);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sessionId = (int)$input->getOption('session');
        $days      = (int)$input->getOption('days');

        if ($sessionId <= 0) {
            $output->writeln('<error>--session must be a positive integer</error>');
            return Command::INVALID;
        }
        if ($days <= 0) {
            $output->writeln('<error>--days must be a positive integer</error>');
            return Command::INVALID;
        }

        $result = $this->handler->start($sessionId, $days, LongActionType::Sleep);

        $output->writeln(sprintf(
            'Slept %d day(s). World day: %d. Session #%d resumed at (%d,%d).',
            $result->daysAdvanced,
            $result->world->getCurrentDay(),
            (int)$result->session->getId(),
            $result->session->getPlayerX(),
            $result->session->getPlayerY(),
        ));

        return Command::SUCCESS;
    }
}

