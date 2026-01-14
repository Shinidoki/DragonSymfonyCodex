<?php

namespace App\Command;

use App\Entity\Character;
use App\Entity\CharacterTechnique;
use App\Entity\TechniqueDefinition;
use App\Repository\TechniqueDefinitionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'game:character:learn-technique',
    description: 'Teach a character a technique definition (by technique code).',
)]
final class GameCharacterLearnTechniqueCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TechniqueDefinitionRepository $techniqueRepository,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('character', null, InputOption::VALUE_REQUIRED, 'Character id');
        $this->addOption('technique', null, InputOption::VALUE_REQUIRED, 'Technique code (e.g. ki_blast)');
        $this->addOption('proficiency', null, InputOption::VALUE_OPTIONAL, 'Initial proficiency (0..100)', 0);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $characterId = (int)$input->getOption('character');
        if ($characterId <= 0) {
            $output->writeln('<error>--character must be a positive integer</error>');
            return Command::INVALID;
        }

        $techniqueCode = strtolower(trim((string)$input->getOption('technique')));
        if ($techniqueCode === '') {
            $output->writeln('<error>--technique must not be empty</error>');
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

        $definition = $this->techniqueRepository->findEnabledByCode($techniqueCode);
        if (!$definition instanceof TechniqueDefinition) {
            $output->writeln('<error>Technique not found (or disabled)</error>');
            return Command::FAILURE;
        }

        $repo = $this->entityManager->getRepository(CharacterTechnique::class);
        $existing = $repo->findOneBy(['character' => $character, 'technique' => $definition]);

        if ($existing instanceof CharacterTechnique) {
            $existing->setProficiency($proficiency);
            $this->entityManager->flush();
            $output->writeln(sprintf('Updated technique %s for character %d (proficiency=%d).', $definition->getCode(), $characterId, $proficiency));
            return Command::SUCCESS;
        }

        $link = new CharacterTechnique($character, $definition, $proficiency);
        $this->entityManager->persist($link);
        $this->entityManager->flush();

        $output->writeln(sprintf('Learned technique %s for character %d (proficiency=%d).', $definition->getCode(), $characterId, $proficiency));

        return Command::SUCCESS;
    }
}

