<?php

namespace App\Command;

use App\Game\Application\World\CreateWorldHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'game:world:create',
    description: 'Create a new world with a deterministic seed.',
)]
final class GameWorldCreateCommand extends Command
{
    public function __construct(private readonly CreateWorldHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('seed', null, InputOption::VALUE_REQUIRED, 'World seed (e.g. earth-0001)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $seed = (string)$input->getOption('seed');
        if (trim($seed) === '') {
            $output->writeln('<error>--seed is required</error>');
            return Command::INVALID;
        }

        $world = $this->handler->create($seed);

        $output->writeln(sprintf('Created world #%d (seed: %s)', (int)$world->getId(), $world->getSeed()));

        return Command::SUCCESS;
    }
}

