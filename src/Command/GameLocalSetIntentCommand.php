<?php

namespace App\Command;

use App\Entity\LocalActor;
use App\Entity\LocalIntent;
use App\Game\Domain\LocalNpc\IntentType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'game:local:set-intent',
    description: 'Set an intent for a local actor.',
)]
final class GameLocalSetIntentCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('actor', null, InputOption::VALUE_REQUIRED, 'Local actor id');
        $this->addOption('type', null, InputOption::VALUE_REQUIRED, 'Intent type (idle|move_to|talk_to|attack)');
        $this->addOption('target', null, InputOption::VALUE_OPTIONAL, 'Target local actor id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $actorId  = (int)$input->getOption('actor');
        $typeRaw  = strtolower((string)$input->getOption('type'));
        $targetId = $input->getOption('target') === null ? null : (int)$input->getOption('target');

        if ($actorId <= 0) {
            $output->writeln('<error>--actor must be a positive integer</error>');
            return Command::INVALID;
        }

        try {
            $type = IntentType::from($typeRaw);
        } catch (\ValueError) {
            $output->writeln('<error>--type must be one of: idle, move_to, talk_to, attack</error>');
            return Command::INVALID;
        }

        if ($targetId !== null && $targetId <= 0) {
            $output->writeln('<error>--target must be a positive integer</error>');
            return Command::INVALID;
        }

        $actor = $this->entityManager->find(LocalActor::class, $actorId);
        if (!$actor instanceof LocalActor) {
            $output->writeln('<error>Local actor not found</error>');
            return Command::FAILURE;
        }

        $intent = new LocalIntent($actor, $type, $targetId);
        $this->entityManager->persist($intent);
        $this->entityManager->flush();

        $output->writeln(sprintf(
            'Created intent #%d for local actor #%d: %s%s',
            (int)$intent->getId(),
            (int)$actor->getId(),
            $intent->getType()->value,
            $intent->getTargetActorId() !== null ? sprintf(' -> %d', $intent->getTargetActorId()) : '',
        ));

        return Command::SUCCESS;
    }
}

