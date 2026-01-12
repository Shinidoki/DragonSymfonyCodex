<?php

namespace App\Command;

use App\Game\Application\Map\GenerateWorldMapHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'game:world:generate-map',
    description: 'Generate (or update) the world map tiles for a world.',
)]
final class GameWorldGenerateMapCommand extends Command
{
    public function __construct(private readonly GenerateWorldMapHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('world', null, InputOption::VALUE_REQUIRED, 'World id');
        $this->addOption('width', null, InputOption::VALUE_REQUIRED, 'Map width (tiles)');
        $this->addOption('height', null, InputOption::VALUE_REQUIRED, 'Map height (tiles)');
        $this->addOption('planet', null, InputOption::VALUE_OPTIONAL, 'Planet name', 'Earth');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $worldId = (int)$input->getOption('world');
        $width   = (int)$input->getOption('width');
        $height  = (int)$input->getOption('height');
        $planet  = (string)$input->getOption('planet');

        if ($worldId <= 0) {
            $output->writeln('<error>--world must be a positive integer</error>');
            return Command::INVALID;
        }
        if ($width <= 0 || $height <= 0) {
            $output->writeln('<error>--width and --height must be positive integers</error>');
            return Command::INVALID;
        }

        $result = $this->handler->generate($worldId, $width, $height, $planet);

        $output->writeln(sprintf(
            'World #%d map: %d total tiles (%d created, %d updated)',
            $worldId,
            $result['total'],
            $result['created'],
            $result['updated'],
        ));

        return Command::SUCCESS;
    }
}

