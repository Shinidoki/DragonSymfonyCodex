<?php

namespace App\Command;

use App\Entity\Character;
use App\Entity\CharacterTransformation;
use App\Game\Domain\Transformations\Transformation;
use App\Repository\CharacterTransformationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'game:character:learn-transformation',
    description: 'Teach a character a transformation proficiency (by transformation code).',
)]
final class GameCharacterLearnTransformationCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface            $entityManager,
        private readonly CharacterTransformationRepository $transformationRepository,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('character', null, InputOption::VALUE_REQUIRED, 'Character id');
        $this->addOption('transformation', null, InputOption::VALUE_REQUIRED, 'Transformation code (e.g. super_saiyan)');
        $this->addOption('proficiency', null, InputOption::VALUE_OPTIONAL, 'Initial proficiency (0..100)', 0);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $characterId = (int)$input->getOption('character');
        if ($characterId <= 0) {
            $output->writeln('<error>--character must be a positive integer</error>');
            return Command::INVALID;
        }

        $raw = strtolower(trim((string)$input->getOption('transformation')));
        if ($raw === '') {
            $output->writeln('<error>--transformation must not be empty</error>');
            return Command::INVALID;
        }

        try {
            $transformation = Transformation::from($raw);
        } catch (\ValueError) {
            $output->writeln('<error>Unknown transformation code</error>');
            return Command::INVALID;
        }

        $proficiency = (int)$input->getOption('proficiency');
        if ($proficiency < 0 || $proficiency > 100) {
            $output->writeln('<error>--proficiency must be in range 0..100</error>');
            return Command::INVALID;
        }

        $character = $this->entityManager->find(Character::class, $characterId);
        if (!$character instanceof Character) {
            $output->writeln('<error>Character not found</error>');
            return Command::FAILURE;
        }

        $existing = $this->transformationRepository->findOneFor($character, $transformation);
        if ($existing instanceof CharacterTransformation) {
            $existing->setProficiency($proficiency);
            $this->entityManager->flush();
            $output->writeln(sprintf('Updated transformation %s for character %d (proficiency=%d).', $transformation->value, $characterId, $proficiency));
            return Command::SUCCESS;
        }

        $link = new CharacterTransformation($character, $transformation, $proficiency);
        $this->entityManager->persist($link);
        $this->entityManager->flush();

        $output->writeln(sprintf('Learned transformation %s for character %d (proficiency=%d).', $transformation->value, $characterId, $proficiency));

        return Command::SUCCESS;
    }
}

