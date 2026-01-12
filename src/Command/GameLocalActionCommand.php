<?php

namespace App\Command;

use App\Game\Application\Local\ApplyLocalActionHandler;
use App\Game\Application\Local\LocalEventLog;
use App\Game\Domain\LocalMap\Direction;
use App\Game\Domain\LocalMap\LocalAction;
use App\Game\Domain\LocalMap\LocalActionType;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'game:local:action',
    description: 'Apply a single tick action to an active local session.',
)]
final class GameLocalActionCommand extends Command
{
    public function __construct(
        private readonly ApplyLocalActionHandler $handler,
        private readonly LocalEventLog           $eventLog,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('session', null, InputOption::VALUE_REQUIRED, 'Local session id');
        $this->addOption('type', null, InputOption::VALUE_REQUIRED, 'Action type (move|wait|talk|attack)');
        $this->addOption('dir', null, InputOption::VALUE_OPTIONAL, 'Direction for move (north|south|east|west)');
        $this->addOption('target', null, InputOption::VALUE_OPTIONAL, 'Target actor id for talk/attack');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sessionId = (int)$input->getOption('session');
        if ($sessionId <= 0) {
            $output->writeln('<error>--session must be a positive integer</error>');
            return Command::INVALID;
        }

        $typeRaw = strtolower((string)$input->getOption('type'));
        try {
            $type = LocalActionType::from($typeRaw);
        } catch (\ValueError) {
            $output->writeln('<error>--type must be move, wait, talk, or attack</error>');
            return Command::INVALID;
        }

        $dir = null;
        if ($type === LocalActionType::Move) {
            $dirRaw = strtolower((string)$input->getOption('dir'));
            try {
                $dir = Direction::from($dirRaw);
            } catch (\ValueError) {
                $output->writeln('<error>--dir must be north, south, east, or west</error>');
                return Command::INVALID;
            }
        }

        $target = null;
        if ($type === LocalActionType::Talk || $type === LocalActionType::Attack) {
            $target = (int)$input->getOption('target');
            if ($target <= 0) {
                $output->writeln('<error>--target must be a positive integer</error>');
                return Command::INVALID;
            }
        }

        $session = $this->handler->apply($sessionId, new LocalAction($type, $dir, $target));

        $output->writeln(sprintf(
            'Tick %d: player at (%d,%d)',
            $session->getCurrentTick(),
            $session->getPlayerX(),
            $session->getPlayerY(),
        ));

        foreach ($this->eventLog->drainMessages((int)$session->getId()) as $message) {
            $output->writeln(sprintf('- %s', $message));
        }

        return Command::SUCCESS;
    }
}
