<?php

namespace App\Game\Application\Simulation;

use App\Entity\World;
use App\Game\Domain\Simulation\SimulationClock;
use App\Game\Domain\Stats\Growth\TrainingIntensity;
use App\Repository\CharacterRepository;
use App\Repository\WorldRepository;
use Doctrine\ORM\EntityManagerInterface;

final class AdvanceDayHandler
{
    public function __construct(
        private readonly WorldRepository        $worldRepository,
        private readonly CharacterRepository    $characterRepository,
        private readonly SimulationClock        $clock,
        private readonly EntityManagerInterface $entityManager,
    )
    {
    }

    public function advance(int $worldId, int $days): AdvanceDayResult
    {
        if ($days < 0) {
            throw new \InvalidArgumentException('Days must be >= 0.');
        }

        $world = $this->worldRepository->find($worldId);
        if (!$world instanceof World) {
            throw new \RuntimeException(sprintf('World not found: %d', $worldId));
        }

        /** @var list<\App\Entity\Character> $characters */
        $characters = $this->characterRepository->findBy(['world' => $world]);

        $this->clock->advanceDays($world, $characters, $days, TrainingIntensity::Normal);
        $this->entityManager->flush();

        return new AdvanceDayResult($world, $characters, $days);
    }
}

