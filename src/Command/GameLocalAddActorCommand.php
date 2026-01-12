<?php

namespace App\Command;

use App\Entity\Character;
use App\Entity\LocalActor;
use App\Entity\LocalSession;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'game:local:add-actor',
    description: 'Add an actor (player or npc) to an existing local session.',
)]
final class GameLocalAddActorCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('session', null, InputOption::VALUE_REQUIRED, 'Local session id');
        $this->addOption('character', null, InputOption::VALUE_REQUIRED, 'Character id');
        $this->addOption('role', null, InputOption::VALUE_OPTIONAL, 'Role (player|npc)', 'npc');
        $this->addOption('x', null, InputOption::VALUE_OPTIONAL, 'Local X', 0);
        $this->addOption('y', null, InputOption::VALUE_OPTIONAL, 'Local Y', 0);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sessionId   = (int)$input->getOption('session');
        $characterId = (int)$input->getOption('character');
        $role        = strtolower((string)$input->getOption('role'));
        $x           = (int)$input->getOption('x');
        $y           = (int)$input->getOption('y');

        if ($sessionId <= 0 || $characterId <= 0) {
            $output->writeln('<error>--session and --character must be positive integers</error>');
            return Command::INVALID;
        }
        if ($x < 0 || $y < 0) {
            $output->writeln('<error>--x and --y must be >= 0</error>');
            return Command::INVALID;
        }
        if (!in_array($role, ['player', 'npc'], true)) {
            $output->writeln('<error>--role must be player or npc</error>');
            return Command::INVALID;
        }

        $session = $this->entityManager->find(LocalSession::class, $sessionId);
        if (!$session instanceof LocalSession) {
            $output->writeln('<error>Local session not found</error>');
            return Command::FAILURE;
        }

        $character = $this->entityManager->find(Character::class, $characterId);
        if (!$character instanceof Character) {
            $output->writeln('<error>Character not found</error>');
            return Command::FAILURE;
        }

        $actor = new LocalActor($session, characterId: (int)$character->getId(), role: $role, x: $x, y: $y);
        $this->entityManager->persist($actor);
        $this->entityManager->flush();

        $output->writeln(sprintf(
            'Added local actor #%d (character %d, role %s) at (%d,%d)',
            (int)$actor->getId(),
            (int)$actor->getCharacterId(),
            $actor->getRole(),
            $actor->getX(),
            $actor->getY(),
        ));

        return Command::SUCCESS;
    }
}

