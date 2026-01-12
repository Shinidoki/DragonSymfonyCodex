<?php

namespace App\Game\Application\Local;

use App\Entity\LocalSession;
use App\Game\Domain\LocalMap\LocalAction;
use Doctrine\ORM\EntityManagerInterface;

final class ApplyLocalActionHandler
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function apply(int $sessionId, LocalAction $action): LocalSession
    {
        $session = $this->entityManager->find(LocalSession::class, $sessionId);
        if (!$session instanceof LocalSession) {
            throw new \RuntimeException(sprintf('Local session not found: %d', $sessionId));
        }
        if (!$session->isActive()) {
            throw new \RuntimeException('Cannot apply action to a suspended local session.');
        }

        (new LocalTurnEngine($this->entityManager))->applyPlayerAction($session, $action);

        $this->entityManager->flush();

        return $session;
    }
}