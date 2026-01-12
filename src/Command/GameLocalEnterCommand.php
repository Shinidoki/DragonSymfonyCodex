<?php

namespace App\Command;

use App\Game\Application\Local\EnterLocalModeHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'game:local:enter',
    description: 'Enter local mode for a character (tick-only simulation).',
)]
final class GameLocalEnterCommand extends Command
{
    public function __construct(private readonly EnterLocalModeHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('character', null, InputOption::VALUE_REQUIRED, 'Character id');
        $this->addOption('width', null, InputOption::VALUE_OPTIONAL, 'Local map width', 8);
        $this->addOption('height', null, InputOption::VALUE_OPTIONAL, 'Local map height', 8);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $characterId = (int)$input->getOption('character');
        $width       = (int)$input->getOption('width');
        $height      = (int)$input->getOption('height');

        if ($characterId <= 0) {
            $output->writeln('<error>--character must be a positive integer</error>');
            return Command::INVALID;
        }

        $session = $this->handler->enter($characterId, $width, $height);

        $output->writeln(sprintf(
            'Entered local mode: session #%d (tile %d,%d; player %d,%d; size %dx%d)',
            (int)$session->getId(),
            $session->getTileX(),
            $session->getTileY(),
            $session->getPlayerX(),
            $session->getPlayerY(),
            $session->getWidth(),
            $session->getHeight(),
        ));

        return Command::SUCCESS;
    }
}

