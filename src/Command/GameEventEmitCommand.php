<?php

namespace App\Command;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\World;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'game:event:emit',
    description: 'Emit a character/world event (simulation/dev tool).',
)]
final class GameEventEmitCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('world', null, InputOption::VALUE_REQUIRED, 'World id');
        $this->addOption('type', null, InputOption::VALUE_REQUIRED, 'Event type (e.g. tournament_announced)');
        $this->addOption('day', null, InputOption::VALUE_OPTIONAL, 'World day to record the event for (default: current)', null);
        $this->addOption('character', null, InputOption::VALUE_OPTIONAL, 'Character id (omit for broadcast/world event)', null);
        $this->addOption('center-x', null, InputOption::VALUE_OPTIONAL, 'Broadcast center X (optional)', null);
        $this->addOption('center-y', null, InputOption::VALUE_OPTIONAL, 'Broadcast center Y (optional)', null);
        $this->addOption('radius', null, InputOption::VALUE_OPTIONAL, 'Broadcast radius (Manhattan; optional)', null);
        $this->addOption('data', null, InputOption::VALUE_OPTIONAL, 'Additional JSON data to merge into event data', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $worldId        = (int)$input->getOption('world');
        $type           = (string)$input->getOption('type');
        $characterIdRaw = $input->getOption('character');

        if ($worldId <= 0) {
            $output->writeln('<error>--world must be a positive integer</error>');
            return Command::INVALID;
        }
        if (trim($type) === '') {
            $output->writeln('<error>--type must not be empty</error>');
            return Command::INVALID;
        }

        $world = $this->entityManager->find(World::class, $worldId);
        if (!$world instanceof World) {
            $output->writeln(sprintf('<error>World not found: %d</error>', $worldId));
            return Command::FAILURE;
        }

        $dayOption = $input->getOption('day');
        $day       = $dayOption === null ? $world->getCurrentDay() : (int)$dayOption;
        if ($day < 0) {
            $output->writeln('<error>--day must be >= 0</error>');
            return Command::INVALID;
        }

        $character = null;
        if ($characterIdRaw !== null) {
            $characterId = (int)$characterIdRaw;
            if ($characterId <= 0) {
                $output->writeln('<error>--character must be a positive integer when provided</error>');
                return Command::INVALID;
            }

            $found = $this->entityManager->find(Character::class, $characterId);
            if (!$found instanceof Character) {
                $output->writeln(sprintf('<error>Character not found: %d</error>', $characterId));
                return Command::FAILURE;
            }

            if ($found->getWorld()->getId() !== $world->getId()) {
                $output->writeln('<error>Character does not belong to the provided world</error>');
                return Command::FAILURE;
            }

            $character = $found;
        }

        $data     = [];
        $dataJson = $input->getOption('data');
        if (is_string($dataJson) && trim($dataJson) !== '') {
            $decoded = json_decode($dataJson, true);
            if (!is_array($decoded)) {
                $output->writeln('<error>--data must be valid JSON object</error>');
                return Command::INVALID;
            }
            /** @var array<string,mixed> $decoded */
            $data = $decoded;
        }

        $cx = $input->getOption('center-x');
        $cy = $input->getOption('center-y');
        $r  = $input->getOption('radius');
        if ($cx !== null || $cy !== null || $r !== null) {
            $centerX = (int)$cx;
            $centerY = (int)$cy;
            $radius  = (int)$r;

            if ($centerX < 0 || $centerY < 0 || $radius < 0) {
                $output->writeln('<error>--center-x/--center-y/--radius must be >= 0 when provided</error>');
                return Command::INVALID;
            }

            $data['center_x'] = $centerX;
            $data['center_y'] = $centerY;
            $data['radius']   = $radius;
        }

        $event = new CharacterEvent($world, $character, $type, $day, $data === [] ? null : $data);
        $this->entityManager->persist($event);
        $this->entityManager->flush();

        $output->writeln(sprintf('Created event #%d (%s) for world #%d on day %d.', (int)$event->getId(), $event->getType(), $worldId, $day));
        if ($character instanceof Character) {
            $output->writeln(sprintf('Character: #%d (%s)', (int)$character->getId(), $character->getName()));
        } else {
            $output->writeln('Character: <broadcast>');
        }

        return Command::SUCCESS;
    }
}

