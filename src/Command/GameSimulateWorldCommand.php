<?php

namespace App\Command;

use App\Entity\CharacterEvent;
use App\Game\Application\Map\GenerateWorldMapHandler;
use App\Game\Application\Simulation\AdvanceDayHandler;
use App\Game\Application\World\CreateWorldHandler;
use App\Game\Application\World\PopulateWorldHandler;
use App\Game\Domain\Power\PowerLevelCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'game:sim:simulate-world',
    description: 'Create a new world and simulate it for N days, printing summary stats.',
)]
final class GameSimulateWorldCommand extends Command
{
    private const int   DEFAULT_MAP_WIDTH  = 16;
    private const int   DEFAULT_MAP_HEIGHT = 16;
    private const int   DEFAULT_NPC_COUNT  = 25;
    private const array NOISE_EVENT_TYPES  = [
        'money_low_employed',
        'money_low_unemployed',
    ];

    public function __construct(
        private readonly CreateWorldHandler      $worldCreator,
        private readonly GenerateWorldMapHandler $mapGenerator,
        private readonly PopulateWorldHandler    $worldPopulator,
        private readonly AdvanceDayHandler       $simulator,
        private readonly PowerLevelCalculator    $power,
        private readonly EntityManagerInterface  $entityManager,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('days', InputArgument::REQUIRED, 'Number of days to simulate');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = (int)$input->getArgument('days');
        if ($days <= 0) {
            $output->writeln('<error>days must be a positive integer</error>');
            return Command::INVALID;
        }

        $seed = sprintf('sim-%s-%06d', (new \DateTimeImmutable())->format('YmdHis'), random_int(0, 999_999));

        $world   = $this->worldCreator->create($seed);
        $worldId = (int)$world->getId();

        $map = $this->mapGenerator->generate($worldId, self::DEFAULT_MAP_WIDTH, self::DEFAULT_MAP_HEIGHT, 'Earth');
        $pop = $this->worldPopulator->populate($worldId, self::DEFAULT_NPC_COUNT);

        $progress = new ProgressBar($output, $days);
        $progress->setFormat('Day %current%/%max% [%bar%] %percent:3s%% | %event%');
        $progress->setMessage('Last event: (none)', 'event');
        $progress->start();

        $result           = null;
        $previousDayEvent = null;

        for ($i = 1; $i <= $days; $i++) {
            $progress->setMessage('Last event: ' . ($previousDayEvent ?? '(none)'), 'event');
            $result = $this->simulator->advance($worldId, 1);

            $previousDayEvent = $this->majorEventSummary($result->world, $result->world->getCurrentDay());
            $progress->advance();
        }

        $progress->finish();
        $output->write("\r\n\r\n");

        if ($result === null) {
            throw new \LogicException('Simulation loop produced no result.');
        }

        $characters      = $result->characters;
        $countCharacters = count($characters);

        $strongest      = null;
        $strongestPower = null;
        foreach ($characters as $c) {
            $p = $this->power->calculate($c->getCoreAttributes());
            if ($strongest === null || $strongestPower === null || $p > $strongestPower) {
                $strongest      = $c;
                $strongestPower = $p;
            }
        }

        $eventsByType = [];
        $totalEvents  = 0;
        /** @var list<CharacterEvent> $events */
        $events = $this->entityManager->getRepository(CharacterEvent::class)->findBy(['world' => $result->world], ['id' => 'ASC']);
        foreach ($events as $e) {
            $totalEvents++;
            $eventsByType[$e->getType()] = ($eventsByType[$e->getType()] ?? 0) + 1;
        }

        arsort($eventsByType);

        $output->writeln(sprintf('Created world #%d (seed: %s)', $worldId, $world->getSeed()));
        $output->writeln(sprintf('Map: %dx%d tiles (%d created, %d updated)', self::DEFAULT_MAP_WIDTH, self::DEFAULT_MAP_HEIGHT, $map['created'], $map['updated']));
        $output->writeln(sprintf('Populated: %d NPC(s)', $pop->created));

        $output->writeln(sprintf('Simulated: %d day(s) (now day %d)', $days, $result->world->getCurrentDay()));

        $output->writeln(sprintf('Characters: %d', $countCharacters));
        $output->writeln('Deaths: 0 (no death system yet)');

        if ($strongest !== null && $strongestPower !== null) {
            $output->writeln(sprintf('Strongest: %s (#%d) power=%d', $strongest->getName(), (int)$strongest->getId(), $strongestPower));
        } else {
            $output->writeln('Strongest: n/a');
        }

        $output->writeln(sprintf('Events: %d', $totalEvents));
        $top = array_slice($eventsByType, 0, 5, true);
        foreach ($top as $type => $n) {
            $output->writeln(sprintf('- %s: %d', $type, $n));
        }

        foreach ($pop->createdByArchetype as $archetype => $n) {
            $output->writeln(sprintf('Archetype %s: %d', $archetype, $n));
        }

        return Command::SUCCESS;
    }

    private function majorEventSummary(\App\Entity\World $world, int $day): ?string
    {
        /** @var list<CharacterEvent> $events */
        $events = $this->entityManager->getRepository(CharacterEvent::class)->findBy(
            ['world' => $world, 'day' => $day],
            ['id' => 'DESC'],
            25,
        );

        foreach ($events as $event) {
            $type = $event->getType();
            if (in_array($type, self::NOISE_EVENT_TYPES, true)) {
                continue;
            }

            $parts = [$type];

            $character = $event->getCharacter();
            if ($character !== null) {
                $parts[] = $character->getName();
            }

            $data = $event->getData();
            if (is_array($data)) {
                $x = $data['center_x'] ?? $data['settlement_x'] ?? null;
                $y = $data['center_y'] ?? $data['settlement_y'] ?? null;
                if (is_int($x) && is_int($y)) {
                    $parts[] = sprintf('(%d,%d)', $x, $y);
                }
            }

            return implode(' ', $parts);
        }

        return null;
    }
}
