<?php

namespace App\Command;

use App\Entity\Character;
use App\Game\Domain\Race;
use App\Repository\WorldRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'game:character:create',
    description: 'Create a character in an existing world.',
)]
final class GameCharacterCreateCommand extends Command
{
    public function __construct(
        private readonly WorldRepository        $worlds,
        private readonly EntityManagerInterface $entityManager,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('world', null, InputOption::VALUE_REQUIRED, 'World id');
        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'Character name');
        $this->addOption('race', null, InputOption::VALUE_REQUIRED, 'Race (saiyan|human|namekian|majin)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $worldId = (int)$input->getOption('world');
        $name    = (string)$input->getOption('name');
        $raceRaw = strtolower((string)$input->getOption('race'));

        if ($worldId <= 0) {
            $output->writeln('<error>--world must be a positive integer</error>');
            return Command::INVALID;
        }

        if (trim($name) === '') {
            $output->writeln('<error>--name is required</error>');
            return Command::INVALID;
        }

        try {
            $race = Race::from($raceRaw);
        } catch (\ValueError) {
            $output->writeln('<error>--race must be one of: saiyan, human, namekian, majin</error>');
            return Command::INVALID;
        }

        $world = $this->worlds->find($worldId);
        if ($world === null) {
            $output->writeln(sprintf('<error>World not found: %d</error>', $worldId));
            return Command::FAILURE;
        }

        $character = new Character($world, $name, $race);

        $this->entityManager->persist($character);
        $this->entityManager->flush();

        $output->writeln(sprintf('Created character #%d (%s, %s) in world #%d', (int)$character->getId(), $character->getName(), $character->getRace()->value, (int)$world->getId()));

        return Command::SUCCESS;
    }
}

