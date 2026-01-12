<?php

namespace App\Game\Application\Local;

use App\Entity\LocalSession;
use Doctrine\ORM\EntityManagerInterface;

final class ExitLocalModeHandler
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function exit(int $sessionId): LocalSession
    {
        $session = $this->entityManager->find(LocalSession::class, $sessionId);
        if (!$session instanceof LocalSession) {
            throw new \RuntimeException(sprintf('Local session not found: %d', $sessionId));
        }

        $session->suspend();
        $this->entityManager->flush();

        return $session;
    }
}

