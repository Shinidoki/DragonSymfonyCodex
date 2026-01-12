<?php

namespace App\Command;

use App\Entity\Character;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'game:character:set-travel',
    description: 'Set a character travel target on the world map.',
)]
final class GameCharacterSetTravelCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('character', null, InputOption::VALUE_REQUIRED, 'Character id');
        $this->addOption('x', null, InputOption::VALUE_REQUIRED, 'Target tile X');
        $this->addOption('y', null, InputOption::VALUE_REQUIRED, 'Target tile Y');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $characterId = (int)$input->getOption('character');
        $x           = (int)$input->getOption('x');
        $y           = (int)$input->getOption('y');

        if ($characterId <= 0) {
            $output->writeln('<error>--character must be a positive integer</error>');
            return Command::INVALID;
        }
        if ($x < 0 || $y < 0) {
            $output->writeln('<error>--x and --y must be >= 0</error>');
            return Command::INVALID;
        }

        $character = $this->entityManager->find(Character::class, $characterId);
        if (!$character instanceof Character) {
            $output->writeln(sprintf('<error>Character not found: %d</error>', $characterId));
            return Command::FAILURE;
        }

        $character->setTravelTarget($x, $y);
        $this->entityManager->flush();

        $output->writeln(sprintf(
            'Character #%d travel target set to (%d,%d)',
            $characterId,
            (int)$character->getTargetTileX(),
            (int)$character->getTargetTileY(),
        ));

        return Command::SUCCESS;
    }
}

