<?php

namespace App\Command;

use App\Game\Application\Local\ExitLocalModeHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'game:local:exit',
    description: 'Exit local mode by suspending a local session.',
)]
final class GameLocalExitCommand extends Command
{
    public function __construct(private readonly ExitLocalModeHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('session', null, InputOption::VALUE_REQUIRED, 'Local session id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sessionId = (int)$input->getOption('session');
        if ($sessionId <= 0) {
            $output->writeln('<error>--session must be a positive integer</error>');
            return Command::INVALID;
        }

        $session = $this->handler->exit($sessionId);

        $output->writeln(sprintf('Exited local mode: session #%d (%s)', (int)$session->getId(), $session->getStatus()));

        return Command::SUCCESS;
    }
}

