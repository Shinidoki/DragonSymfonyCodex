<?php

namespace App\Game\Application\Local;

use App\Entity\LocalSession;
use App\Game\Domain\LocalMap\LocalAction;
use App\Game\Domain\LocalMap\LocalActionType;
use App\Game\Domain\LocalMap\LocalCoord;
use App\Game\Domain\LocalMap\LocalMapSize;
use App\Game\Domain\LocalMap\LocalMovement;
use Doctrine\ORM\EntityManagerInterface;

final class ApplyLocalActionHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LocalMovement          $movement = new LocalMovement(),
    )
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

        if ($action->type === LocalActionType::Move) {
            $current = new LocalCoord($session->getPlayerX(), $session->getPlayerY());
            $size    = new LocalMapSize($session->getWidth(), $session->getHeight());
            $next    = $this->movement->move($current, $action->direction, $size);
            $session->setPlayerPosition($next->x, $next->y);
        }

        $session->incrementTick();
        $this->entityManager->flush();

        return $session;
    }
}

