<?php

namespace App\Game\Application\Local;

use App\Entity\LocalEvent;
use App\Entity\LocalSession;
use App\Game\Domain\LocalMap\VisibilityRadius;
use Doctrine\ORM\EntityManagerInterface;

final class LocalEventLog
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function record(LocalSession $session, int $eventX, int $eventY, string $message, VisibilityRadius $radius): void
    {
        if ($eventX < 0 || $eventY < 0) {
            throw new \InvalidArgumentException('event coordinates must be >= 0.');
        }

        $distance = abs($session->getPlayerX() - $eventX) + abs($session->getPlayerY() - $eventY);
        if ($distance > $radius->tiles) {
            return;
        }

        $event = new LocalEvent(
            session: $session,
            tick: $session->getCurrentTick(),
            x: $eventX,
            y: $eventY,
            message: $message,
        );

        $this->entityManager->persist($event);
        $this->entityManager->flush();
    }

    /**
     * @return list<string>
     */
    public function drainMessages(int $sessionId): array
    {
        $session = $this->entityManager->find(LocalSession::class, $sessionId);
        if (!$session instanceof LocalSession) {
            throw new \RuntimeException(sprintf('Local session not found: %d', $sessionId));
        }

        /** @var list<LocalEvent> $events */
        $events = $this->entityManager->getRepository(LocalEvent::class)->findBy(['session' => $session], ['id' => 'ASC']);

        $messages = [];
        foreach ($events as $event) {
            $messages[] = $event->getMessage();
            $this->entityManager->remove($event);
        }

        if ($messages !== []) {
            $this->entityManager->flush();
        }

        return $messages;
    }
}

