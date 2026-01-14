<?php

namespace App\Game\Application\Local;

use App\Entity\Character;
use App\Entity\LocalActor;
use App\Entity\LocalCombat;
use App\Entity\LocalCombatant;
use App\Entity\LocalSession;
use App\Game\Application\Local\Combat\CombatResolver;
use App\Game\Domain\LocalMap\LocalAction;
use App\Game\Domain\LocalMap\LocalActionType;
use App\Game\Domain\LocalMap\LocalCoord;
use App\Game\Domain\LocalMap\LocalMapSize;
use App\Game\Domain\LocalMap\LocalMovement;
use App\Game\Domain\LocalMap\VisibilityRadius;
use App\Game\Domain\LocalTurns\TurnScheduler;
use App\Game\Domain\Transformations\TransformationService;
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

        // Advance NPC actions until the player is next to act.
        while ($this->peekNextActorId($session, $actors) !== (int)$playerActor->getId()) {
            $this->executeNextNpcAction($session, $actors, $playerActor);
        }

        // Consume the player turn.
        $next = $this->consumeNextActorId($session, $actors);
        if ($next !== (int)$playerActor->getId()) {
            throw new \LogicException('Expected player to be next to act.');
        }

        $this->incrementTick($session);

        if ($playerActor->isCharging()) {
            $this->advanceChargingTurn($session, $playerActor);
        } else {
            $this->applyPlayerLocalAction($session, $playerActor, $playerAction);
            $playerActor->setPosition($session->getPlayerX(), $session->getPlayerY());
        }

        // Advance NPC actions until the player is next to act again.
        while ($this->peekNextActorId($session, $actors) !== (int)$playerActor->getId()) {
            $this->executeNextNpcAction($session, $actors, $playerActor);
        }
    }

    /**
     * @param list<LocalActor> $actors
     * @return array<int,array{id:int,speed:int,meter:int}>
     */
    private function buildTurnState(LocalSession $session, array $actors): array
    {
        $defeatedActorIds = $this->findDefeatedActorIds($session);

        $characterIds = array_values(array_unique(array_map(static fn (LocalActor $a): int => $a->getCharacterId(), $actors)));

        $charactersById = [];
        if ($characterIds !== []) {
            /** @var list<Character> $characters */
            $characters = $this->entityManager->getRepository(Character::class)->findBy(['id' => $characterIds]);
            foreach ($characters as $character) {
                $charactersById[(int)$character->getId()] = $character;
            }
        }

        $transformations = new TransformationService();

        $state = [];
        foreach ($actors as $actor) {
            if (isset($defeatedActorIds[(int)$actor->getId()])) {
                continue;
            }

            $speed = 1;
            $character = $charactersById[$actor->getCharacterId()] ?? null;
            if ($character instanceof Character) {
                $effective = $transformations->effectiveAttributes($character->getCoreAttributes(), $character->getTransformationState());
                $speed     = max(1, $effective->speed);
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
     * @param list<LocalActor> $actors
     */
    private function peekNextActorId(LocalSession $session, array $actors): int
    {
        $state = $this->buildTurnState($session, $actors);
        $copy  = $state;
        return $this->scheduler->pickNextActorId($copy);
    }

    /**
     * @param list<LocalActor> $actors
     */
    private function executeNextNpcAction(LocalSession $session, array $actors, LocalActor $playerActor): void
    {
        $nextId = $this->consumeNextActorId($session, $actors);

        $nextActor = $this->findActorById($actors, $nextId);
        if (!$nextActor instanceof LocalActor) {
            throw new \RuntimeException(sprintf('LocalActor not found in session: %d', $nextId));
        }
        if ($nextActor->getRole() === 'player') {
            throw new \LogicException('Tried to execute NPC action on a player turn.');
        }

        $this->incrementTick($session);

        if ($nextActor->isCharging()) {
            $this->advanceChargingTurn($session, $nextActor);
            return;
        }

        ($this->npcTickRunner ?? new LocalNpcTickRunner($this->entityManager))->applyNpcTurn($session, $nextActor, $playerActor);
    }

    private function advanceChargingTurn(LocalSession $session, LocalActor $actor): void
    {
        $actor->decrementChargingTick();

        if ($actor->getChargingTicksRemaining() > 0) {
            (new LocalEventLog($this->entityManager))->record(
                $session,
                $actor->getX(),
                $actor->getY(),
                sprintf('%s continues charging.', $this->characterName($actor->getCharacterId())),
                new VisibilityRadius(2),
            );

            return;
        }

        $techniqueCode = $actor->getChargingTechniqueCode();
        $targetId      = $actor->getChargingTargetActorId();
        $actor->clearCharging();

        if ($techniqueCode === null || $targetId === null) {
            return;
        }

        (new CombatResolver($this->entityManager))->useTechnique($session, $actor, $targetId, $techniqueCode);
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

    /**
     * @param list<LocalActor> $actors
     */
    private function consumeNextActorId(LocalSession $session, array $actors): int
    {
        $state = $this->buildTurnState($session, $actors);
        $next  = $this->scheduler->pickNextActorId($state);
        $this->syncTurnMeters($actors, $state);
        return $next;
    }

    private function applyPlayerLocalAction(LocalSession $session, LocalActor $playerActor, LocalAction $action): void
    {
        if ($action->type === LocalActionType::Wait) {
            return;
        }

        if ($action->type === LocalActionType::Move) {
            $current = new LocalCoord($session->getPlayerX(), $session->getPlayerY());
            $size    = new LocalMapSize($session->getWidth(), $session->getHeight());
            $next    = $this->movement->move($current, $action->direction, $size);
            $session->setPlayerPosition($next->x, $next->y);
            return;
        }

        if ($action->type === LocalActionType::Talk) {
            $target = $action->targetActorId !== null
                ? $this->entityManager->find(LocalActor::class, $action->targetActorId)
                : null;

            if (!$target instanceof LocalActor || (int)$target->getSession()->getId() !== (int)$session->getId()) {
                (new LocalEventLog($this->entityManager))->record($session, $playerActor->getX(), $playerActor->getY(), 'No valid target.', new VisibilityRadius(2));
                return;
            }

            $distance = abs($playerActor->getX() - $target->getX()) + abs($playerActor->getY() - $target->getY());
            if ($distance > 1) {
                (new LocalEventLog($this->entityManager))->record($session, $playerActor->getX(), $playerActor->getY(), 'Target is too far away.', new VisibilityRadius(2));
                return;
            }

            $playerName = $this->characterName($playerActor->getCharacterId());
            $targetName = $this->characterName($target->getCharacterId());
            (new LocalEventLog($this->entityManager))->record(
                $session,
                $playerActor->getX(),
                $playerActor->getY(),
                sprintf('%s talks to %s.', $playerName, $targetName),
                new VisibilityRadius(2),
            );
            return;
        }

        if ($action->type === LocalActionType::Attack) {
            if ($action->targetActorId === null) {
                throw new \InvalidArgumentException('Attack action requires a target actor id.');
            }

            (new CombatResolver($this->entityManager))->attack($session, $playerActor, $action->targetActorId);
            return;
        }

        if ($action->type === LocalActionType::Technique) {
            if ($action->targetActorId === null || $action->techniqueCode === null || trim($action->techniqueCode) === '') {
                throw new \InvalidArgumentException('Technique action requires target actor id and technique code.');
            }

            (new CombatResolver($this->entityManager))->useTechnique($session, $playerActor, $action->targetActorId, $action->techniqueCode);
            return;
        }

        throw new \LogicException(sprintf('Unsupported local action type: %s', $action->type->value));
    }

    private function incrementTick(LocalSession $session): void
    {
        $session->incrementTick();
    }

    /**
     * @return array<int,true>
     */
    private function findDefeatedActorIds(LocalSession $session): array
    {
        $combat = $this->entityManager->getRepository(LocalCombat::class)->findOneBy(['session' => $session]);
        if (!$combat instanceof LocalCombat) {
            return [];
        }

        /** @var list<LocalCombatant> $combatants */
        $combatants = $this->entityManager->getRepository(LocalCombatant::class)->findBy(['combat' => $combat]);

        $defeated = [];
        foreach ($combatants as $combatant) {
            if ($combatant->isDefeated()) {
                $defeated[$combatant->getActorId()] = true;
            }
        }

        return $defeated;
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
