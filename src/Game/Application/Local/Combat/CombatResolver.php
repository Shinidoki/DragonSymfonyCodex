<?php

namespace App\Game\Application\Local\Combat;

use App\Entity\Character;
use App\Entity\CharacterTechnique;
use App\Entity\LocalActor;
use App\Entity\LocalCombat;
use App\Entity\LocalCombatant;
use App\Entity\LocalSession;
use App\Entity\TechniqueDefinition;
use App\Game\Application\Local\LocalEventLog;
use App\Game\Domain\LocalMap\AimMode;
use App\Game\Domain\LocalMap\Direction;
use App\Game\Domain\LocalMap\LocalAction;
use App\Game\Domain\LocalMap\LocalActionType;
use App\Game\Domain\LocalMap\VisibilityRadius;
use App\Game\Domain\Techniques\Execution\TechniqueDamageCalculator;
use App\Game\Domain\Techniques\Execution\TechniqueUseCalculator;
use App\Game\Domain\Techniques\Targeting\LocalTargetSelector;
use App\Game\Domain\Transformations\TransformationService;
use App\Repository\TechniqueDefinitionRepository;
use Doctrine\ORM\EntityManagerInterface;

final class CombatResolver
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function useTechniqueFromAction(LocalSession $session, LocalActor $attacker, LocalAction $action): void
    {
        if ($action->type !== LocalActionType::Technique || $action->techniqueCode === null) {
            throw new \InvalidArgumentException('Action must be a technique action.');
        }

        $this->useTechniqueWithAim(
            session: $session,
            attacker: $attacker,
            techniqueCode: $action->techniqueCode,
            aimMode: $action->aimMode,
            targetActorId: $action->targetActorId,
            direction: $action->direction,
            targetX: $action->targetX,
            targetY: $action->targetY,
        );
    }

    public function useTechnique(LocalSession $session, LocalActor $attacker, int $targetActorId, string $techniqueCode): void
    {
        $this->useTechniqueWithAim(
            session: $session,
            attacker: $attacker,
            techniqueCode: $techniqueCode,
            aimMode: AimMode::Actor,
            targetActorId: $targetActorId,
            direction: null,
            targetX: null,
            targetY: null,
        );
    }

    public function attack(LocalSession $session, LocalActor $attacker, int $targetActorId): void
    {
        $target = $this->entityManager->find(LocalActor::class, $targetActorId);
        if (!$target instanceof LocalActor || (int)$target->getSession()->getId() !== (int)$session->getId()) {
            $this->recordEvent($session, $attacker->getX(), $attacker->getY(), 'No valid target.');
            return;
        }

        $distance = abs($attacker->getX() - $target->getX()) + abs($attacker->getY() - $target->getY());
        if ($distance > 1) {
            $this->recordEvent($session, $attacker->getX(), $attacker->getY(), 'Target is too far away.');
            return;
        }

        $combat            = $this->getOrCreateCombat($session);
        $attackerCombatant = $this->getOrCreateCombatant($combat, $attacker);
        $defenderCombatant = $this->getOrCreateCombatant($combat, $target);

        $damage = $this->damagePerHit($attacker, $target);
        $defenderCombatant->applyDamage($damage, $session->getCurrentTick());

        $attackerName = $this->characterName($attacker->getCharacterId());
        $defenderName = $this->characterName($target->getCharacterId());

        $this->recordEvent(
            $session,
            $attacker->getX(),
            $attacker->getY(),
            sprintf('%s attacks %s for %d damage.', $attackerName, $defenderName, $damage),
        );

        if ($defenderCombatant->isDefeated()) {
            $this->recordEvent(
                $session,
                $attacker->getX(),
                $attacker->getY(),
                sprintf('%s defeats %s.', $attackerName, $defenderName),
            );

            $combat->resolve();
        }

        $this->entityManager->persist($combat);
        $this->entityManager->persist($attackerCombatant);
        $this->entityManager->persist($defenderCombatant);
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

    private function damagePerHit(LocalActor $attacker, LocalActor $defender): int
    {
        $transformations = new TransformationService();

        $attackerCharacter = $this->entityManager->find(Character::class, $attacker->getCharacterId());
        $defenderCharacter = $this->entityManager->find(Character::class, $defender->getCharacterId());

        $attackerStrength = 1;
        if ($attackerCharacter instanceof Character) {
            $attackerEffective = $transformations->effectiveAttributes($attackerCharacter->getCoreAttributes(), $attackerCharacter->getTransformationState());
            $attackerStrength  = $attackerEffective->strength;
        }

        $defenderDurability = 1;
        if ($defenderCharacter instanceof Character) {
            $defenderEffective = $transformations->effectiveAttributes($defenderCharacter->getCoreAttributes(), $defenderCharacter->getTransformationState());
            $defenderDurability = $defenderEffective->durability;
        }

        return max(1, $attackerStrength - intdiv($defenderDurability, 2));
    }

    private function damageForKiBlast(LocalActor $attacker, LocalActor $defender): int
    {
        $transformations = new TransformationService();

        $attackerCharacter = $this->entityManager->find(Character::class, $attacker->getCharacterId());
        $defenderCharacter = $this->entityManager->find(Character::class, $defender->getCharacterId());

        $attackerKiControl = 1;
        if ($attackerCharacter instanceof Character) {
            $attackerEffective = $transformations->effectiveAttributes($attackerCharacter->getCoreAttributes(), $attackerCharacter->getTransformationState());
            $attackerKiControl = $attackerEffective->kiControl;
        }

        $defenderDurability = 1;
        if ($defenderCharacter instanceof Character) {
            $defenderEffective = $transformations->effectiveAttributes($defenderCharacter->getCoreAttributes(), $defenderCharacter->getTransformationState());
            $defenderDurability = $defenderEffective->durability;
        }

        return max(1, $attackerKiControl - intdiv($defenderDurability, 2));
    }

    private function useTechniqueWithAim(
        LocalSession $session,
        LocalActor   $attacker,
        string       $techniqueCode,
        AimMode      $aimMode,
        ?int         $targetActorId,
        ?Direction   $direction,
        ?int         $targetX,
        ?int         $targetY,
    ): void
    {
        $techniqueCode = strtolower(trim($techniqueCode));
        if ($techniqueCode === '') {
            $this->recordEvent($session, $attacker->getX(), $attacker->getY(), 'No technique selected.');
            return;
        }

        /** @var TechniqueDefinitionRepository $techniqueRepo */
        $techniqueRepo = $this->entityManager->getRepository(TechniqueDefinition::class);

        $definition = $techniqueRepo->findEnabledByCode($techniqueCode);
        if (!$definition instanceof TechniqueDefinition) {
            $this->recordEvent($session, $attacker->getX(), $attacker->getY(), 'Unknown technique.');
            return;
        }

        $config = $definition->getConfig();
        if (!isset($config['aimModes'], $config['delivery'])) {
            $this->recordEvent($session, $attacker->getX(), $attacker->getY(), 'Technique is misconfigured.');
            return;
        }

        $allowedAimModes = array_map('strtolower', is_array($config['aimModes']) ? $config['aimModes'] : []);
        if (!in_array($aimMode->value, $allowedAimModes, true)) {
            $this->recordEvent($session, $attacker->getX(), $attacker->getY(), 'That technique cannot be aimed that way.');
            return;
        }

        $attackerCharacter = $this->entityManager->find(Character::class, $attacker->getCharacterId());
        if (!$attackerCharacter instanceof Character) {
            $this->recordEvent($session, $attacker->getX(), $attacker->getY(), 'No character for attacker.');
            return;
        }

        $knowledge = $this->entityManager->getRepository(CharacterTechnique::class)->findOneBy([
            'character' => $attackerCharacter,
            'technique' => $definition,
        ]);
        if (!$knowledge instanceof CharacterTechnique) {
            $this->recordEvent($session, $attacker->getX(), $attacker->getY(), 'You do not know that technique.');
            return;
        }

        if ($attacker->hasPreparedTechnique()) {
            $this->recordEvent($session, $attacker->getX(), $attacker->getY(), 'Already charging.');
            return;
        }

        $range     = (int)($config['range'] ?? 0);
        $delivery  = (string)$config['delivery'];
        $piercing  = isset($config['piercing']) ? (string)$config['piercing'] : null;
        $aoeRadius = isset($config['aoeRadius']) ? (int)$config['aoeRadius'] : null;

        $aimX = $targetX;
        $aimY = $targetY;

        if ($aimMode === AimMode::Actor) {
            if ($targetActorId === null) {
                $this->recordEvent($session, $attacker->getX(), $attacker->getY(), 'No valid target.');
                return;
            }

            $actorTarget = $this->entityManager->find(LocalActor::class, $targetActorId);
            if (!$actorTarget instanceof LocalActor || (int)$actorTarget->getSession()->getId() !== (int)$session->getId()) {
                $this->recordEvent($session, $attacker->getX(), $attacker->getY(), 'No valid target.');
                return;
            }

            $aimX = $actorTarget->getX();
            $aimY = $actorTarget->getY();
        }

        /** @var list<LocalActor> $actors */
        $actors = $this->entityManager->getRepository(LocalActor::class)->findBy(['session' => $session], ['id' => 'ASC']);

        $targets = (new LocalTargetSelector())->selectTargets(
            attacker: $attacker,
            actors: $actors,
            aimMode: $aimMode,
            direction: $direction,
            targetX: $aimX,
            targetY: $aimY,
            range: $range,
            delivery: $delivery,
            piercing: $piercing,
            aoeRadius: $aoeRadius,
        );

        $combat            = $this->getOrCreateCombat($session);
        $attackerCombatant = $this->getOrCreateCombatant($combat, $attacker);

        $useCalc    = new TechniqueUseCalculator();
        $damageCalc = new TechniqueDamageCalculator();

        $effectiveCost = $useCalc->effectiveKiCost($definition, $knowledge);
        $success       = $useCalc->rollSuccess($definition, $knowledge, (int)$session->getId(), $session->getCurrentTick(), (int)$attacker->getId());
        $spent         = $success ? $effectiveCost : (int)ceil($effectiveCost * $useCalc->failureKiCostMultiplier($definition));

        if ($spent > 0 && !$attackerCombatant->spendKi($spent)) {
            $this->recordEvent($session, $attacker->getX(), $attacker->getY(), 'Not enough Ki.');
            $this->entityManager->persist($combat);
            $this->entityManager->persist($attackerCombatant);
            return;
        }

        if (!$success) {
            $this->recordEvent(
                $session,
                $attacker->getX(),
                $attacker->getY(),
                sprintf('%s fails to use %s.', $attackerCharacter->getName(), $definition->getName()),
            );

            $this->entityManager->persist($combat);
            $this->entityManager->persist($attackerCombatant);
            return;
        }

        if ($targets === []) {
            $this->recordEvent(
                $session,
                $attacker->getX(),
                $attacker->getY(),
                sprintf('%s uses %s, but it hits nothing.', $attackerCharacter->getName(), $definition->getName()),
            );

            $knowledge->incrementProficiency(1);
            $this->entityManager->persist($knowledge);
            $this->entityManager->persist($combat);
            $this->entityManager->persist($attackerCombatant);
            return;
        }

        $anyDefeated = false;

        foreach ($targets as $target) {
            $defenderCharacter = $this->entityManager->find(Character::class, $target->getCharacterId());
            if (!$defenderCharacter instanceof Character) {
                continue;
            }

            $defenderCombatant = $this->getOrCreateCombatant($combat, $target);
            $damage            = $damageCalc->damageFor($definition, $knowledge, $attackerCharacter, $defenderCharacter);
            $defenderCombatant->applyDamage($damage, $session->getCurrentTick());

            $this->recordEvent(
                $session,
                $attacker->getX(),
                $attacker->getY(),
                sprintf('%s uses %s on %s for %d damage.', $attackerCharacter->getName(), $definition->getName(), $defenderCharacter->getName(), $damage),
            );

            if ($defenderCombatant->isDefeated()) {
                $anyDefeated = true;
                $this->recordEvent(
                    $session,
                    $attacker->getX(),
                    $attacker->getY(),
                    sprintf('%s defeats %s.', $attackerCharacter->getName(), $defenderCharacter->getName()),
                );
            }

            $this->entityManager->persist($defenderCombatant);
        }

        if ($anyDefeated) {
            $combat->resolve();
        }

        $knowledge->incrementProficiency(1);
        $this->entityManager->persist($knowledge);
        $this->entityManager->persist($combat);
        $this->entityManager->persist($attackerCombatant);
    }

    private function characterName(int $characterId): string
    {
        $character = $this->entityManager->find(Character::class, $characterId);
        if ($character instanceof Character) {
            return $character->getName();
        }

        return sprintf('Character#%d', $characterId);
    }

    private function recordEvent(LocalSession $session, int $eventX, int $eventY, string $message): void
    {
        (new LocalEventLog($this->entityManager))->record(
            session: $session,
            eventX: $eventX,
            eventY: $eventY,
            message: $message,
            radius: new VisibilityRadius(2),
        );
    }
}
