<?php

namespace App\Game\Application\Local;

use App\Entity\Character;
use App\Entity\CharacterTechnique;
use App\Entity\CharacterTransformation;
use App\Entity\LocalActor;
use App\Entity\LocalCombat;
use App\Entity\LocalCombatant;
use App\Entity\LocalSession;
use App\Entity\TechniqueDefinition;
use App\Game\Application\Local\Combat\CombatResolver;
use App\Game\Domain\LocalMap\LocalAction;
use App\Game\Domain\LocalMap\LocalActionType;
use App\Game\Domain\LocalMap\LocalCoord;
use App\Game\Domain\LocalMap\LocalMapSize;
use App\Game\Domain\LocalMap\LocalMovement;
use App\Game\Domain\LocalMap\VisibilityRadius;
use App\Game\Domain\LocalTurns\TurnScheduler;
use App\Game\Domain\Techniques\Execution\TechniqueUseCalculator;
use App\Game\Domain\Techniques\Prepared\PreparedTechniquePhase;
use App\Game\Domain\Techniques\TechniqueType;
use App\Game\Domain\Transformations\Transformation;
use App\Game\Domain\Transformations\TransformationService;
use App\Repository\TechniqueDefinitionRepository;
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

        $this->applyPlayerLocalAction($session, $playerActor, $playerAction);
        $playerActor->setPosition($session->getPlayerX(), $session->getPlayerY());

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

        if ($nextActor->hasPreparedTechnique()) {
            $this->advancePreparedAfterAction($session, $nextActor, skipChargeMessage: false);
            return;
        }

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
        if ($action->type === LocalActionType::Cancel) {
            if (!$playerActor->hasPreparedTechnique()) {
                (new LocalEventLog($this->entityManager))->record($session, $playerActor->getX(), $playerActor->getY(), 'Nothing to cancel.', new VisibilityRadius(2));
                return;
            }

            $code = $playerActor->getPreparedTechniqueCode();
            $playerActor->clearPreparedTechnique();

            (new LocalEventLog($this->entityManager))->record(
                $session,
                $playerActor->getX(),
                $playerActor->getY(),
                sprintf('%s cancels %s.', $this->characterName($playerActor->getCharacterId()), $code ?? 'the technique'),
                new VisibilityRadius(2),
            );
            return;
        }

        if ($playerActor->hasPreparedTechnique()) {
            $this->applyPlayerActionWhilePrepared($session, $playerActor, $action);
            return;
        }

        if ($action->type === LocalActionType::Transform) {
            $this->applyTransformAction($session, $playerActor, $action->transformation, requireProficiency50: false);
            return;
        }

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
            $this->applyTalkAction($session, $playerActor, $action->targetActorId);
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
            if ($action->techniqueCode === null || trim($action->techniqueCode) === '') {
                throw new \InvalidArgumentException('Technique action requires a technique code.');
            }

            $techniqueCode = strtolower(trim($action->techniqueCode));

            /** @var TechniqueDefinitionRepository $techniqueRepo */
            $techniqueRepo = $this->entityManager->getRepository(TechniqueDefinition::class);
            $definition    = $techniqueRepo->findEnabledByCode($techniqueCode);
            if (!$definition instanceof TechniqueDefinition) {
                (new LocalEventLog($this->entityManager))->record($session, $playerActor->getX(), $playerActor->getY(), 'Unknown technique.', new VisibilityRadius(2));
                return;
            }

            if ($definition->getType() === TechniqueType::Charged) {
                $this->startChargingTechnique($session, $playerActor, $definition);
                return;
            }

            (new CombatResolver($this->entityManager))->useTechniqueFromAction($session, $playerActor, $action);
            return;
        }

        throw new \LogicException(sprintf('Unsupported local action type: %s', $action->type->value));
    }

    private function applyPlayerActionWhilePrepared(LocalSession $session, LocalActor $playerActor, LocalAction $action): void
    {
        $preparedCode = $playerActor->getPreparedTechniqueCode();
        if ($preparedCode === null) {
            $playerActor->clearPreparedTechnique();
            return;
        }

        /** @var TechniqueDefinitionRepository $techniqueRepo */
        $techniqueRepo = $this->entityManager->getRepository(TechniqueDefinition::class);
        $definition    = $techniqueRepo->findEnabledByCode($preparedCode);
        if (!$definition instanceof TechniqueDefinition) {
            $playerActor->clearPreparedTechnique();
            (new LocalEventLog($this->entityManager))->record($session, $playerActor->getX(), $playerActor->getY(), 'Prepared technique is no longer available.', new VisibilityRadius(2));
            return;
        }

        $allowMove = (bool)($definition->getConfig()['allowMoveWhilePrepared'] ?? false);

        if ($action->type === LocalActionType::Move && !$allowMove) {
            (new LocalEventLog($this->entityManager))->record($session, $playerActor->getX(), $playerActor->getY(), 'Cannot move while charging.', new VisibilityRadius(2));
            $this->advancePreparedAfterAction($session, $playerActor, skipChargeMessage: false);
            return;
        }

        if ($action->type === LocalActionType::Attack) {
            (new LocalEventLog($this->entityManager))->record($session, $playerActor->getX(), $playerActor->getY(), 'Cannot attack while charging.', new VisibilityRadius(2));
            $this->advancePreparedAfterAction($session, $playerActor, skipChargeMessage: false);
            return;
        }

        if ($action->type === LocalActionType::Technique) {
            $code = strtolower(trim((string)$action->techniqueCode));
            if ($code !== $preparedCode) {
                (new LocalEventLog($this->entityManager))->record($session, $playerActor->getX(), $playerActor->getY(), 'Cannot use another technique while charging.', new VisibilityRadius(2));
                $this->advancePreparedAfterAction($session, $playerActor, skipChargeMessage: false);
                return;
            }

            if ($playerActor->getPreparedPhase() !== PreparedTechniquePhase::Ready) {
                (new LocalEventLog($this->entityManager))->record($session, $playerActor->getX(), $playerActor->getY(), 'Still charging.', new VisibilityRadius(2));
                $this->advancePreparedAfterAction($session, $playerActor, skipChargeMessage: false);
                return;
            }

            // Release: aim is chosen at release time.
            $playerActor->clearPreparedTechnique();
            (new CombatResolver($this->entityManager))->useTechniqueFromAction($session, $playerActor, $action);
            return;
        }

        if ($action->type === LocalActionType::Move) {
            $current = new LocalCoord($session->getPlayerX(), $session->getPlayerY());
            $size    = new LocalMapSize($session->getWidth(), $session->getHeight());
            $next    = $this->movement->move($current, $action->direction, $size);
            $session->setPlayerPosition($next->x, $next->y);
            $this->advancePreparedAfterAction($session, $playerActor, skipChargeMessage: false);
            return;
        }

        if ($action->type === LocalActionType::Talk) {
            $this->applyTalkAction($session, $playerActor, $action->targetActorId);
            $this->advancePreparedAfterAction($session, $playerActor, skipChargeMessage: false);
            return;
        }

        if ($action->type === LocalActionType::Transform) {
            $this->applyTransformAction($session, $playerActor, $action->transformation, requireProficiency50: true);
            $this->advancePreparedAfterAction($session, $playerActor, skipChargeMessage: false);
            return;
        }

        // Wait or unsupported actions while prepared just advance the preparation.
        $this->advancePreparedAfterAction($session, $playerActor, skipChargeMessage: false);
    }

    private function applyTalkAction(LocalSession $session, LocalActor $playerActor, ?int $targetActorId): void
    {
        $target = $targetActorId !== null
            ? $this->entityManager->find(LocalActor::class, $targetActorId)
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
    }

    private function applyTransformAction(LocalSession $session, LocalActor $actor, ?Transformation $transformation, bool $requireProficiency50): void
    {
        if ($transformation === null) {
            throw new \InvalidArgumentException('Transform action requires a transformation.');
        }

        $character = $this->entityManager->find(Character::class, $actor->getCharacterId());
        if (!$character instanceof Character) {
            (new LocalEventLog($this->entityManager))->record($session, $actor->getX(), $actor->getY(), 'No character for actor.', new VisibilityRadius(2));
            return;
        }

        $knowledge = $this->entityManager->getRepository(CharacterTransformation::class)->findOneBy([
            'character'      => $character,
            'transformation' => $transformation,
        ]);

        if (!$knowledge instanceof CharacterTransformation) {
            (new LocalEventLog($this->entityManager))->record(
                $session,
                $actor->getX(),
                $actor->getY(),
                sprintf('%s cannot transform into %s.', $character->getName(), $transformation->value),
                new VisibilityRadius(2),
            );
            return;
        }

        if ($requireProficiency50 && $knowledge->getProficiency() < 50) {
            (new LocalEventLog($this->entityManager))->record(
                $session,
                $actor->getX(),
                $actor->getY(),
                sprintf('%s is not proficient enough to transform while charging.', $character->getName()),
                new VisibilityRadius(2),
            );
            return;
        }

        $service = new TransformationService();
        $state   = $character->getTransformationState();

        try {
            if ($state->active === null) {
                $character->setTransformationState($service->activate($state, $transformation));
                (new LocalEventLog($this->entityManager))->record(
                    $session,
                    $actor->getX(),
                    $actor->getY(),
                    sprintf('%s transforms into %s.', $character->getName(), $transformation->value),
                    new VisibilityRadius(2),
                );
            } else {
                $character->setTransformationState($service->deactivate($state));
                (new LocalEventLog($this->entityManager))->record(
                    $session,
                    $actor->getX(),
                    $actor->getY(),
                    sprintf('%s reverts to normal.', $character->getName()),
                    new VisibilityRadius(2),
                );
            }
        } catch (\RuntimeException $e) {
            (new LocalEventLog($this->entityManager))->record($session, $actor->getX(), $actor->getY(), $e->getMessage(), new VisibilityRadius(2));
            return;
        }

        $this->entityManager->persist($character);
    }

    private function startChargingTechnique(LocalSession $session, LocalActor $actor, TechniqueDefinition $definition): void
    {
        if ($actor->hasPreparedTechnique()) {
            (new LocalEventLog($this->entityManager))->record($session, $actor->getX(), $actor->getY(), 'Already charging.', new VisibilityRadius(2));
            return;
        }

        $character = $this->entityManager->find(Character::class, $actor->getCharacterId());
        if (!$character instanceof Character) {
            (new LocalEventLog($this->entityManager))->record($session, $actor->getX(), $actor->getY(), 'No character for attacker.', new VisibilityRadius(2));
            return;
        }

        $knowledge = $this->entityManager->getRepository(CharacterTechnique::class)->findOneBy([
            'character' => $character,
            'technique' => $definition,
        ]);
        if (!$knowledge instanceof CharacterTechnique) {
            (new LocalEventLog($this->entityManager))->record($session, $actor->getX(), $actor->getY(), 'You do not know that technique.', new VisibilityRadius(2));
            return;
        }

        $useCalc       = new TechniqueUseCalculator();
        $effectiveCost = $useCalc->effectiveKiCost($definition, $knowledge);

        $combat    = $this->getOrCreateCombat($session);
        $combatant = $this->getOrCreateCombatant($combat, $actor);

        if ($combatant->getCurrentKi() < $effectiveCost) {
            (new LocalEventLog($this->entityManager))->record($session, $actor->getX(), $actor->getY(), 'Not enough Ki.', new VisibilityRadius(2));
            $this->entityManager->persist($combat);
            $this->entityManager->persist($combatant);
            return;
        }

        $config      = $definition->getConfig();
        $chargeTicks = max(0, (int)($config['chargeTicks'] ?? 0));

        $actor->startPreparingTechnique($definition->getCode(), PreparedTechniquePhase::Charging, $chargeTicks, $session->getCurrentTick());
        (new LocalEventLog($this->entityManager))->record(
            $session,
            $actor->getX(),
            $actor->getY(),
            sprintf('%s starts charging %s.', $character->getName(), $definition->getName()),
            new VisibilityRadius(2),
        );

        $this->advancePreparedAfterAction($session, $actor, skipChargeMessage: true);
    }

    private function advancePreparedAfterAction(LocalSession $session, LocalActor $actor, bool $skipChargeMessage): void
    {
        $code  = $actor->getPreparedTechniqueCode();
        $phase = $actor->getPreparedPhase();

        if ($code === null || $phase === null) {
            $actor->clearPreparedTechnique();
            return;
        }

        /** @var TechniqueDefinitionRepository $techniqueRepo */
        $techniqueRepo = $this->entityManager->getRepository(TechniqueDefinition::class);
        $definition    = $techniqueRepo->findEnabledByCode($code);
        if (!$definition instanceof TechniqueDefinition) {
            $actor->clearPreparedTechnique();
            (new LocalEventLog($this->entityManager))->record($session, $actor->getX(), $actor->getY(), 'Prepared technique is no longer available.', new VisibilityRadius(2));
            return;
        }

        if ($phase === PreparedTechniquePhase::Charging) {
            if ($actor->getPreparedTicksRemaining() <= 0) {
                $actor->markPreparedReady();
                (new LocalEventLog($this->entityManager))->record(
                    $session,
                    $actor->getX(),
                    $actor->getY(),
                    sprintf('%s finishes charging %s.', $this->characterName($actor->getCharacterId()), $definition->getName()),
                    new VisibilityRadius(2),
                );
                return;
            }

            $actor->decrementPreparedTick();

            if ($actor->getPreparedTicksRemaining() > 0) {
                if (!$skipChargeMessage) {
                    (new LocalEventLog($this->entityManager))->record(
                        $session,
                        $actor->getX(),
                        $actor->getY(),
                        sprintf('%s continues charging.', $this->characterName($actor->getCharacterId())),
                        new VisibilityRadius(2),
                    );
                }

                return;
            }

            $actor->markPreparedReady();
            (new LocalEventLog($this->entityManager))->record(
                $session,
                $actor->getX(),
                $actor->getY(),
                sprintf('%s finishes charging %s.', $this->characterName($actor->getCharacterId()), $definition->getName()),
                new VisibilityRadius(2),
            );

            return;
        }

        if ($phase !== PreparedTechniquePhase::Ready) {
            return;
        }

        $character = $this->entityManager->find(Character::class, $actor->getCharacterId());
        if (!$character instanceof Character) {
            $actor->clearPreparedTechnique();
            return;
        }

        $knowledge = $this->entityManager->getRepository(CharacterTechnique::class)->findOneBy([
            'character' => $character,
            'technique' => $definition,
        ]);
        if (!$knowledge instanceof CharacterTechnique) {
            $actor->clearPreparedTechnique();
            return;
        }

        $combat    = $this->getOrCreateCombat($session);
        $combatant = $this->getOrCreateCombatant($combat, $actor);

        $useCalc       = new TechniqueUseCalculator();
        $effectiveCost = $useCalc->effectiveKiCost($definition, $knowledge);

        $holdKi = max(0, (int)($definition->getConfig()['holdKiPerTick'] ?? 0));
        if ($holdKi <= 0) {
            return;
        }

        $currentKi = $combatant->getCurrentKi();
        if ($currentKi < $effectiveCost) {
            $actor->clearPreparedTechnique();
            (new LocalEventLog($this->entityManager))->record($session, $actor->getX(), $actor->getY(), 'Not enough Ki to keep holding the charge.', new VisibilityRadius(2));
            $this->entityManager->persist($combat);
            $this->entityManager->persist($combatant);
            return;
        }

        if (($currentKi - $holdKi) < $effectiveCost) {
            $actor->clearPreparedTechnique();
            (new LocalEventLog($this->entityManager))->record($session, $actor->getX(), $actor->getY(), 'Not enough Ki to keep holding the charge.', new VisibilityRadius(2));
            $this->entityManager->persist($combat);
            $this->entityManager->persist($combatant);
            return;
        }

        if (!$combatant->spendKi($holdKi)) {
            $actor->clearPreparedTechnique();
            (new LocalEventLog($this->entityManager))->record($session, $actor->getX(), $actor->getY(), 'Not enough Ki to keep holding the charge.', new VisibilityRadius(2));
            $this->entityManager->persist($combat);
            $this->entityManager->persist($combatant);
            return;
        }

        $this->entityManager->persist($combat);
        $this->entityManager->persist($combatant);
    }

    private function getOrCreateCombat(LocalSession $session): LocalCombat
    {
        $existing = $this->entityManager->getRepository(LocalCombat::class)->findOneBy(['session' => $session]);
        if ($existing instanceof LocalCombat) {
            return $existing;
        }

        $combat = new LocalCombat($session);
        $this->entityManager->persist($combat);

        return $combat;
    }

    private function getOrCreateCombatant(LocalCombat $combat, LocalActor $actor): LocalCombatant
    {
        $repo = $this->entityManager->getRepository(LocalCombatant::class);

        $existing = $repo->findOneBy(['combat' => $combat, 'actorId' => (int)$actor->getId()]);
        if ($existing instanceof LocalCombatant) {
            return $existing;
        }

        $maxHp     = $this->maxHp($actor);
        $maxKi     = $this->maxKi($actor);
        $combatant = new LocalCombatant($combat, actorId: (int)$actor->getId(), maxHp: $maxHp, maxKi: $maxKi);
        $this->entityManager->persist($combatant);

        return $combatant;
    }

    private function maxKi(LocalActor $actor): int
    {
        $transformations = new TransformationService();

        $character = $this->entityManager->find(Character::class, $actor->getCharacterId());
        if (!$character instanceof Character) {
            return 9;
        }

        $effective = $transformations->effectiveAttributes($character->getCoreAttributes(), $character->getTransformationState());

        return 5 + ($effective->kiCapacity * 3) + $effective->kiControl;
    }

    private function maxHp(LocalActor $actor): int
    {
        $transformations = new TransformationService();

        $character = $this->entityManager->find(Character::class, $actor->getCharacterId());
        if (!$character instanceof Character) {
            return 13;
        }

        $effective = $transformations->effectiveAttributes($character->getCoreAttributes(), $character->getTransformationState());

        return 10 + ($effective->endurance * 2) + $effective->durability;
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
