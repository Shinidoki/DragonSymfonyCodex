<?php

namespace App\Game\Application\Local;

use App\Entity\Character;
use App\Entity\LocalActor;
use App\Entity\LocalSession;
use App\Game\Domain\LocalMap\LocalAction;
use App\Game\Domain\LocalMap\LocalActionType;
use App\Game\Domain\LocalMap\LocalCoord;
use App\Game\Domain\LocalMap\LocalMapSize;
use App\Game\Domain\LocalMap\LocalMovement;
use App\Game\Domain\LocalTurns\TurnScheduler;
use Doctrine\ORM\EntityManagerInterface;

final class LocalTurnEngine
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TurnScheduler $scheduler = new TurnScheduler(),
        private readonly LocalMovement $movement = new LocalMovement(),
        private readonly ?LocalNpcTickRunner $npcTickRunner = null,
    )
    {
    }

    public function applyPlayerAction(LocalSession $session, LocalAction $playerAction): void
    {
        if (!$session->isActive()) {
            throw new \RuntimeException('Cannot apply action to a suspended local session.');
        }

        $actors = $this->entityManager->getRepository(LocalActor::class)->findBy(
            ['session' => $session],
            ['id' => 'ASC'],
        );

        $playerActor = null;
        foreach ($actors as $actor) {
            if ($actor->getRole() === 'player') {
                $playerActor = $actor;
                break;
            }
        }
        if (!$playerActor instanceof LocalActor) {
            throw new \RuntimeException('Local session has no player actor.');
        }

        $state = $this->buildTurnState($actors);

        // Advance NPC actions until the player is next to act.
        while ($this->peekNextActorId($state) !== (int)$playerActor->getId()) {
            $this->executeNextNpcAction($session, $actors, $state, $playerActor);
        }

        // Consume the player turn.
        $next = $this->scheduler->pickNextActorId($state);
        if ($next !== (int)$playerActor->getId()) {
            throw new \LogicException('Expected player to be next to act.');
        }
        $this->syncTurnMeters($actors, $state);

        $this->incrementTick($session);
        $this->applyPlayerLocalAction($session, $playerAction);
        $playerActor->setPosition($session->getPlayerX(), $session->getPlayerY());

        // Advance NPC actions until the player is next to act again.
        while ($this->peekNextActorId($state) !== (int)$playerActor->getId()) {
            $this->executeNextNpcAction($session, $actors, $state, $playerActor);
        }

        // Persist final turn meters even if we stopped on a peek.
        $this->syncTurnMeters($actors, $state);
    }

    /**
     * @param list<LocalActor> $actors
     * @return array<int,array{id:int,speed:int,meter:int}>
     */
    private function buildTurnState(array $actors): array
    {
        $characterIds = array_values(array_unique(array_map(static fn (LocalActor $a): int => $a->getCharacterId(), $actors)));

        $charactersById = [];
        if ($characterIds !== []) {
            /** @var list<Character> $characters */
            $characters = $this->entityManager->getRepository(Character::class)->findBy(['id' => $characterIds]);
            foreach ($characters as $character) {
                $charactersById[(int)$character->getId()] = $character;
            }
        }

        $state = [];
        foreach ($actors as $actor) {
            $speed = 1;
            $character = $charactersById[$actor->getCharacterId()] ?? null;
            if ($character instanceof Character) {
                $speed = max(1, $character->getSpeed());
            }

            $state[] = [
                'id' => (int)$actor->getId(),
                'speed' => $speed,
                'meter' => $actor->getTurnMeter(),
            ];
        }

        return $state;
    }

    /**
     * @param array<int,array{id:int,speed:int,meter:int}> $state
     */
    private function peekNextActorId(array $state): int
    {
        $copy = $state;
        return $this->scheduler->pickNextActorId($copy);
    }

    /**
     * @param list<LocalActor> $actors
     * @param array<int,array{id:int,speed:int,meter:int}> $state
     */
    private function executeNextNpcAction(LocalSession $session, array $actors, array &$state, LocalActor $playerActor): void
    {
        $nextId = $this->scheduler->pickNextActorId($state);
        $this->syncTurnMeters($actors, $state);

        $nextActor = $this->findActorById($actors, $nextId);
        if (!$nextActor instanceof LocalActor) {
            throw new \RuntimeException(sprintf('LocalActor not found in session: %d', $nextId));
        }
        if ($nextActor->getRole() === 'player') {
            throw new \LogicException('Tried to execute NPC action on a player turn.');
        }

        $this->incrementTick($session);
        ($this->npcTickRunner ?? new LocalNpcTickRunner($this->entityManager))->applyNpcTurn($session, $nextActor, $playerActor);
    }

    /**
     * @param list<LocalActor> $actors
     */
    private function findActorById(array $actors, int $id): ?LocalActor
    {
        foreach ($actors as $actor) {
            if ((int)$actor->getId() === $id) {
                return $actor;
            }
        }

        return null;
    }

    /**
     * @param list<LocalActor> $actors
     * @param array<int,array{id:int,speed:int,meter:int}> $state
     */
    private function syncTurnMeters(array $actors, array $state): void
    {
        $meters = [];
        foreach ($state as $row) {
            $meters[(int)$row['id']] = (int)$row['meter'];
        }

        foreach ($actors as $actor) {
            $id = (int)$actor->getId();
            if (!isset($meters[$id])) {
                continue;
            }

            $actor->setTurnMeter($meters[$id]);
        }
    }

    private function applyPlayerLocalAction(LocalSession $session, LocalAction $action): void
    {
        if ($action->type !== LocalActionType::Move) {
            return;
        }

        $current = new LocalCoord($session->getPlayerX(), $session->getPlayerY());
        $size    = new LocalMapSize($session->getWidth(), $session->getHeight());
        $next    = $this->movement->move($current, $action->direction, $size);
        $session->setPlayerPosition($next->x, $next->y);
    }

    private function incrementTick(LocalSession $session): void
    {
        $session->incrementTick();
    }
}