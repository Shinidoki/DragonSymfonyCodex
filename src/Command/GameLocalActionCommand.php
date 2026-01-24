<?php

namespace App\Command;

use App\Game\Application\Local\ApplyLocalActionHandler;
use App\Game\Application\Local\LocalEventLog;
use App\Game\Domain\LocalMap\Direction;
use App\Game\Domain\LocalMap\LocalAction;
use App\Game\Domain\LocalMap\LocalActionType;
use App\Game\Domain\Transformations\Transformation;
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
        $this->addOption('type', null, InputOption::VALUE_REQUIRED, 'Action type (move|wait|talk|attack|technique|cancel|transform)');
        $this->addOption('dir', null, InputOption::VALUE_OPTIONAL, 'Direction for move (north|south|east|west)');
        $this->addOption('target', null, InputOption::VALUE_OPTIONAL, 'Target actor id for talk/attack');
        $this->addOption('technique', null, InputOption::VALUE_OPTIONAL, 'Technique code for type=technique (e.g. ki_blast)');
        $this->addOption('x', null, InputOption::VALUE_OPTIONAL, 'Target X for point-aim techniques');
        $this->addOption('y', null, InputOption::VALUE_OPTIONAL, 'Target Y for point-aim techniques');
        $this->addOption('transformation', null, InputOption::VALUE_OPTIONAL, 'Transformation code for type=transform (e.g. super_saiyan)');
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
            $output->writeln('<error>--type must be move, wait, talk, attack, technique, cancel, or transform</error>');
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

        $techniqueCode = null;
        $targetX = null;
        $targetY = null;
        $aimDir = null;
        $aimTarget = null;
        $transformation = null;
        if ($type === LocalActionType::Technique) {
            $techniqueRaw = strtolower(trim((string)$input->getOption('technique')));
            if ($techniqueRaw === '') {
                $output->writeln('<error>--technique is required when --type=technique</error>');
                return Command::INVALID;
            }

            $techniqueCode = $techniqueRaw;

            $hasTarget = $input->getOption('target') !== null;
            $hasDir    = $input->getOption('dir') !== null;
            $hasX      = $input->getOption('x') !== null;
            $hasY      = $input->getOption('y') !== null;

            $aimCount = 0;
            if ($hasTarget) {
                $aimCount++;
            }
            if ($hasDir) {
                $aimCount++;
            }
            if ($hasX || $hasY) {
                $aimCount++;
            }

            if ($aimCount > 1) {
                $output->writeln('<error>Provide only one aim: --target OR --dir OR --x/--y</error>');
                return Command::INVALID;
            }

            if ($hasTarget) {
                $aimTarget = (int)$input->getOption('target');
                if ($aimTarget <= 0) {
                    $output->writeln('<error>--target must be a positive integer</error>');
                    return Command::INVALID;
                }
            }

            if ($hasDir) {
                $dirRaw = strtolower((string)$input->getOption('dir'));
                try {
                    $aimDir = Direction::from($dirRaw);
                } catch (\ValueError) {
                    $output->writeln('<error>--dir must be north, south, east, or west</error>');
                    return Command::INVALID;
                }
            }

            if ($hasX || $hasY) {
                if (!$hasX || !$hasY) {
                    $output->writeln('<error>Both --x and --y are required for point aim</error>');
                    return Command::INVALID;
                }

                $targetX = (int)$input->getOption('x');
                $targetY = (int)$input->getOption('y');
                if ($targetX < 0 || $targetY < 0) {
                    $output->writeln('<error>--x and --y must be >= 0</error>');
                    return Command::INVALID;
                }
            }
        }

        if ($type === LocalActionType::Transform) {
            $raw = strtolower(trim((string)$input->getOption('transformation')));
            if ($raw === '') {
                $output->writeln('<error>--transformation is required when --type=transform</error>');
                return Command::INVALID;
            }

            try {
                $transformation = Transformation::from($raw);
            } catch (\ValueError) {
                $output->writeln('<error>Unknown transformation code</error>');
                return Command::INVALID;
            }
        }

        $session = $this->handler->apply($sessionId, new LocalAction($type, $dir ?? $aimDir, $target ?? $aimTarget, $techniqueCode, $targetX, $targetY, $transformation));

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
