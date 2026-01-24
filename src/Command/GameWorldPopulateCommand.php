<?php

namespace App\Command;

use App\Game\Application\World\PopulateWorldHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'game:world:populate',
    description: 'Populate a world with seeded NPCs (for background simulation).',
)]
final class GameWorldPopulateCommand extends Command
{
    public function __construct(private readonly PopulateWorldHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('world', null, InputOption::VALUE_REQUIRED, 'World id');
        $this->addOption('count', null, InputOption::VALUE_OPTIONAL, 'Number of NPCs to create', 25);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $worldId = (int)$input->getOption('world');
        $count   = (int)$input->getOption('count');

        if ($worldId <= 0) {
            $output->writeln('<error>--world must be a positive integer</error>');
            return Command::INVALID;
        }
        if ($count <= 0) {
            $output->writeln('<error>--count must be a positive integer</error>');
            return Command::INVALID;
        }

        try {
            $result = $this->handler->populate($worldId, $count);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
            return Command::FAILURE;
        }

        $output->writeln(sprintf('World #%d populated with %d NPC(s).', (int)$result->world->getId(), $result->created));
        foreach ($result->createdByArchetype as $archetype => $n) {
            $output->writeln(sprintf('- %s: %d', $archetype, $n));
        }

        return Command::SUCCESS;
    }
}

