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
use App\Game\Domain\LocalMap\VisibilityRadius;
use App\Repository\TechniqueDefinitionRepository;
use App\Game\Domain\Transformations\TransformationService;
use Doctrine\ORM\EntityManagerInterface;

final class CombatResolver
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function useTechnique(LocalSession $session, LocalActor $attacker, int $targetActorId, string $techniqueCode): void
    {
        $target = $this->entityManager->find(LocalActor::class, $targetActorId);
        if (!$target instanceof LocalActor || (int)$target->getSession()->getId() !== (int)$session->getId()) {
            $this->recordEvent($session, $attacker->getX(), $attacker->getY(), 'No valid target.');
            return;
        }

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

        $config = $definition->getConfig();
        $range  = (int)($config['range'] ?? 1);
        $distance = abs($attacker->getX() - $target->getX()) + abs($attacker->getY() - $target->getY());
        if ($distance > $range) {
            $this->recordEvent($session, $attacker->getX(), $attacker->getY(), 'Target is too far away.');
            return;
        }

        $combat            = $this->getOrCreateCombat($session);
        $attackerCombatant = $this->getOrCreateCombatant($combat, $attacker);
        $defenderCombatant = $this->getOrCreateCombatant($combat, $target);

        $cost = (int)($config['kiCost'] ?? 1);
        if (!$attackerCombatant->spendKi($cost)) {
            $this->recordEvent($session, $attacker->getX(), $attacker->getY(), 'Not enough Ki.');
            $this->entityManager->persist($combat);
            $this->entityManager->persist($attackerCombatant);
            $this->entityManager->persist($defenderCombatant);
            return;
        }

        // Temporary MVP wiring: treat all techniques like a "blast" until executors land (next task).
        $damage = $this->damageForKiBlast($attacker, $target);

        $defenderCombatant->applyDamage($damage, $session->getCurrentTick());

        $attackerName = $this->characterName($attacker->getCharacterId());
        $defenderName = $this->characterName($target->getCharacterId());

        $this->recordEvent(
            $session,
            $attacker->getX(),
            $attacker->getY(),
            sprintf('%s uses %s on %s for %d damage.', $attackerName, $definition->getName(), $defenderName, $damage),
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
