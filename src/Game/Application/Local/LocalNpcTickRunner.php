<?php

namespace App\Game\Application\Local;

use App\Entity\Character;
use App\Entity\LocalActor;
use App\Entity\LocalEvent;
use App\Entity\LocalIntent;
use App\Entity\LocalSession;
use App\Game\Domain\LocalNpc\IntentType;
use Doctrine\ORM\EntityManagerInterface;

final class LocalNpcTickRunner
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * MVP: advance each NPC actor by at most one tick action.
     */
    public function advanceNpcTurns(LocalSession $session): void
    {
        if (!$session->isActive()) {
            return;
        }

        $playerActor = $this->entityManager->getRepository(LocalActor::class)->findOneBy([
            'session' => $session,
            'role'    => 'player',
        ]);
        if (!$playerActor instanceof LocalActor) {
            return;
        }

        /** @var list<LocalActor> $npcs */
        $npcs = $this->entityManager->getRepository(LocalActor::class)->findBy(
            ['session' => $session, 'role' => 'npc'],
            ['id' => 'ASC'],
        );

        foreach ($npcs as $npc) {
            $intent = $this->entityManager->getRepository(LocalIntent::class)->findOneBy(
                ['actor' => $npc],
                ['id' => 'DESC'],
            );
            if (!$intent instanceof LocalIntent) {
                continue;
            }

            $this->applyIntent($session, $npc, $playerActor, $intent);
        }

        $this->entityManager->flush();
    }

    private function applyIntent(LocalSession $session, LocalActor $npc, LocalActor $playerActor, LocalIntent $intent): void
    {
        $type = $intent->getType();
        if ($type === IntentType::Idle) {
            return;
        }

        $target = $intent->getTargetActorId() !== null
            ? $this->entityManager->find(LocalActor::class, $intent->getTargetActorId())
            : null;

        if (!$target instanceof LocalActor) {
            $this->entityManager->remove($intent);
            return;
        }

        $distance = abs($npc->getX() - $target->getX()) + abs($npc->getY() - $target->getY());

        if (($type === IntentType::TalkTo || $type === IntentType::Attack) && $distance <= 1) {
            $session->incrementTick();
            $this->recordProximityEvent($session, $npc, $playerActor, $type, $target);
            $this->entityManager->remove($intent);
            return;
        }

        if ($type === IntentType::MoveTo || $type === IntentType::TalkTo || $type === IntentType::Attack) {
            $next = $this->stepToward($npc->getX(), $npc->getY(), $target->getX(), $target->getY());
            $npc->setPosition($next['x'], $next['y']);
            $session->incrementTick();
        }
    }

    /**
     * @return array{x:int,y:int}
     */
    private function stepToward(int $x, int $y, int $targetX, int $targetY): array
    {
        if ($x !== $targetX) {
            $dx = $x < $targetX ? 1 : -1;
            return ['x' => max(0, $x + $dx), 'y' => $y];
        }

        if ($y !== $targetY) {
            $dy = $y < $targetY ? 1 : -1;
            return ['x' => $x, 'y' => max(0, $y + $dy)];
        }

        return ['x' => $x, 'y' => $y];
    }

    private function recordProximityEvent(LocalSession $session, LocalActor $npc, LocalActor $playerActor, IntentType $type, LocalActor $target): void
    {
        $playerDistance = abs($playerActor->getX() - $npc->getX()) + abs($playerActor->getY() - $npc->getY());
        if ($playerDistance > 2) {
            return;
        }

        $npcName    = $this->characterName($npc->getCharacterId());
        $targetName = $this->characterName($target->getCharacterId());

        $message = match ($type) {
            IntentType::TalkTo => sprintf('%s starts talking to %s.', $npcName, $targetName),
            IntentType::Attack => sprintf('%s attacks %s!', $npcName, $targetName),
            default => null,
        };

        if ($message === null) {
            return;
        }

        $event = new LocalEvent($session, $session->getCurrentTick(), $npc->getX(), $npc->getY(), $message);
        $this->entityManager->persist($event);
    }

    private function characterName(int $characterId): string
    {
        $character = $this->entityManager->find(Character::class, $characterId);
        if ($character instanceof Character) {
            return $character->getName();
        }

        return sprintf('Character#%d', $characterId);
    }
}

