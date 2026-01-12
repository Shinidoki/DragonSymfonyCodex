<?php

namespace App\Game\Application\Local;

use App\Entity\Character;
use App\Entity\LocalSession;
use App\Entity\World;
use App\Game\Domain\Simulation\SimulationClock;
use App\Game\Domain\Stats\Growth\TrainingIntensity;
use App\Game\Domain\Training\TrainingContext;
use Doctrine\ORM\EntityManagerInterface;

final class StartLongActionHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SimulationClock        $clock,
    )
    {
    }

    public function start(int $sessionId, int $days, LongActionType $type, ?TrainingContext $trainingContext = null): LongActionResult
    {
        if ($days <= 0) {
            throw new \InvalidArgumentException('days must be positive.');
        }

        $session = $this->entityManager->find(LocalSession::class, $sessionId);
        if (!$session instanceof LocalSession) {
            throw new \RuntimeException(sprintf('Local session not found: %d', $sessionId));
        }
        if (!$session->isActive()) {
            throw new \RuntimeException('Local session must be active to start a long action.');
        }

        if ($type === LongActionType::Train && !$trainingContext instanceof TrainingContext) {
            throw new \InvalidArgumentException('Training requires a TrainingContext.');
        }

        $world = $this->entityManager->find(World::class, $session->getWorldId());
        if (!$world instanceof World) {
            throw new \RuntimeException(sprintf('World not found: %d', $session->getWorldId()));
        }

        $character = $this->entityManager->find(Character::class, $session->getCharacterId());
        if (!$character instanceof Character) {
            throw new \RuntimeException(sprintf('Character not found: %d', $session->getCharacterId()));
        }

        $session->suspend();
        $this->entityManager->flush();

        /** @var list<Character> $characters */
        $characters = $this->entityManager->getRepository(Character::class)->findBy(['world' => $world]);

        $multiplier = null;
        if ($type === LongActionType::Train) {
            $multiplier = $trainingContext->multiplier();
        }

        $this->clock->advanceDaysForLongAction(
            world: $world,
            characters: $characters,
            days: $days,
            intensity: TrainingIntensity::Normal,
            playerCharacterId: (int)$character->getId(),
            trainingMultiplier: $multiplier,
        );

        $this->entityManager->flush();

        $session->resume();
        $this->entityManager->flush();

        return new LongActionResult($world, $character, $session, $days);
    }
}

