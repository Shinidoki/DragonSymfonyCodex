<?php

namespace App\Game\Domain\Combat\SimulatedCombat;

use App\Entity\CharacterTechnique;
use App\Entity\TechniqueDefinition;
use App\Game\Domain\LocalTurns\TurnScheduler;
use App\Game\Domain\Random\PhpRandomizer;
use App\Game\Domain\Random\RandomizerInterface;
use App\Game\Domain\Techniques\Execution\TechniqueUseCalculator;
use App\Game\Domain\Techniques\Prepared\PreparedTechniquePhase;
use App\Game\Domain\Techniques\TechniqueType;
use App\Game\Domain\Transformations\Transformation;
use App\Game\Domain\Transformations\TransformationService;

final class SimulatedCombatResolver
{
    public function __construct(
        private readonly RandomizerInterface    $rng = new PhpRandomizer(),
        private readonly TurnScheduler          $scheduler = new TurnScheduler(),
        private readonly TransformationService  $transformations = new TransformationService(),
        private readonly TechniqueUseCalculator $useCalc = new TechniqueUseCalculator(),
    )
    {
    }

    /**
     * @param list<SimulatedCombatant> $combatants
     */
    public function resolve(array $combatants, CombatRules $rules): SimulatedCombatResult
    {
        if (count($combatants) < 2) {
            throw new \InvalidArgumentException('combatants must have at least 2 participants.');
        }

        $states = [];
        foreach ($combatants as $c) {
            if (!$c instanceof SimulatedCombatant) {
                throw new \InvalidArgumentException('combatants must be SimulatedCombatant instances.');
            }

            $id = $c->character->getId();
            if ($id === null || (int)$id <= 0) {
                throw new \InvalidArgumentException('All characters must have an id (persisted entity).');
            }

            $effective = $this->transformations->effectiveAttributes($c->character->getCoreAttributes(), $c->character->getTransformationState());

            $maxHp = 10 + ($effective->endurance * 2) + $effective->durability;
            $maxHp = max(1, $maxHp);

            $maxKi = 5 + ($effective->kiCapacity * 3) + $effective->kiControl;
            $maxKi = max(1, $maxKi);

            $state = new CombatantState(
                character: $c->character,
                teamId: $c->teamId,
                techniques: $c->techniques,
                transformations: $c->transformations,
                maxHp: $maxHp,
                maxKi: $maxKi,
            );

            $states[(int)$id] = $state;
        }

        $log          = [];
        $defeated     = [];
        $actionNumber = 0;

        while (true) {
            $alive = $this->aliveStates($states);
            if (count($alive) <= 1) {
                break;
            }

            if (!$rules->allowFriendlyFire) {
                $aliveTeams = [];
                foreach ($alive as $s) {
                    $aliveTeams[$s->teamId] = true;
                }
                if (count($aliveTeams) <= 1) {
                    break;
                }
            }

            if ($actionNumber >= $rules->maxActions) {
                break;
            }

            $turnState = [];
            foreach ($alive as $s) {
                $effective = $this->transformations->effectiveAttributes($s->character->getCoreAttributes(), $s->transformationState);
                $speed     = max(1, $effective->speed);

                $turnState[] = [
                    'id'    => (int)$s->character->getId(),
                    'speed' => $speed,
                    'meter' => $s->turnMeter,
                ];
            }

            $nextId = $this->scheduler->pickNextActorId($turnState);

            // Sync meters back.
            foreach ($turnState as $row) {
                $id = $row['id'];
                if (isset($states[$id])) {
                    $states[$id]->turnMeter = $row['meter'];
                }
            }

            $actor = $states[$nextId] ?? null;
            if (!$actor instanceof CombatantState || $actor->isDefeated()) {
                continue;
            }

            $actionNumber++;

            $this->applyAction($actor, $states, $rules, $log, $defeated);
        }

        $winnerId = $this->pickWinnerId($states, $rules);

        return new SimulatedCombatResult(
            winnerCharacterId: $winnerId,
            defeatedCharacterIds: $defeated,
            actions: $actionNumber,
            log: $log,
        );
    }

    /**
     * @param array<int,CombatantState> $states
     * @param list<string>              $log
     * @param list<int>                 $defeated
     */
    private function applyAction(CombatantState $actor, array $states, CombatRules $rules, array &$log, array &$defeated): void
    {
        if ($actor->hasPreparedTechnique()) {
            if ($actor->preparedPhase === PreparedTechniquePhase::Charging) {
                $this->advanceCharging($actor, $log);
                return;
            }

            if ($actor->preparedPhase === PreparedTechniquePhase::Ready) {
                $code = $actor->preparedTechniqueCode;
                $actor->clearPreparedTechnique();

                if ($code !== null) {
                    $this->useTechniqueByCode($actor, $code, $states, $rules, $log, $defeated);
                }

                return;
            }
        }

        if ($rules->allowTransform && $this->shouldTransformNow($actor)) {
            $this->toggleTransformation($actor, $log);
            return;
        }

        $chosen = $this->chooseTechnique($actor, $states, $rules);
        if ($chosen !== null) {
            $definition = $chosen->getTechnique();

            if ($definition->getType() === TechniqueType::Charged) {
                $this->startCharging($actor, $definition, $chosen, $log);
                $this->advanceCharging($actor, $log, skipContinueMessage: true);
                return;
            }

            $this->useTechnique($actor, $definition, $chosen, $states, $rules, $log, $defeated);
            return;
        }

        $this->basicAttack($actor, $states, $rules, $log, $defeated);
    }

    /**
     * @param array<int,CombatantState> $states
     */
    private function chooseTechnique(CombatantState $actor, array $states, CombatRules $rules): ?CharacterTechnique
    {
        $enemies = $this->enemyStates($actor, $states, $rules);
        if ($enemies === []) {
            return null;
        }

        $best      = null;
        $bestScore = null;

        foreach ($actor->techniques as $knowledge) {
            if (!$knowledge instanceof CharacterTechnique) {
                continue;
            }

            $definition = $knowledge->getTechnique();
            if (!$definition->isEnabled()) {
                continue;
            }

            $effectiveCost = $this->useCalc->effectiveKiCost($definition, $knowledge);

            // If we cannot afford the attempt (even the failure spend could be smaller, but we need at least 1 Ki to try), skip.
            if ($effectiveCost > 0 && $actor->currentKi <= 0) {
                continue;
            }

            if ($definition->getType() === TechniqueType::Charged) {
                // Starting charge requires we can pay the full cost at release time.
                if ($actor->currentKi < $effectiveCost) {
                    continue;
                }
            } else {
                // Non-charged: require at least the potential spend.
                if ($effectiveCost > 0 && $actor->currentKi < 1) {
                    continue;
                }
            }

            $score = $this->expectedTechniqueDamage($actor, $knowledge, $enemies, $definition, $rules);

            if ($best === null || $bestScore === null || $score > $bestScore) {
                $best      = $knowledge;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * @param list<CombatantState> $enemies
     */
    private function expectedTechniqueDamage(
        CombatantState      $attacker,
        CharacterTechnique  $knowledge,
        array               $enemies,
        TechniqueDefinition $definition,
        CombatRules         $rules,
    ): float
    {
        $p = $this->useCalc->successChance($definition, $knowledge);

        $targets = $this->selectTargets($attacker, $enemies, $definition, $rules);
        if ($targets === []) {
            return 0.0;
        }

        $sum = 0;
        foreach ($targets as $t) {
            $sum += $this->techniqueDamage($definition, $knowledge, $attacker, $t);
        }

        $chargePenalty = 1.0;
        if ($definition->getType() === TechniqueType::Charged) {
            $chargeTicks   = max(0, (int)($definition->getConfig()['chargeTicks'] ?? 0));
            $chargePenalty = 1.0 / (1.0 + $chargeTicks);
        }

        return ($sum * $p) * $chargePenalty;
    }

    /**
     * @param list<CombatantState> $enemies
     *
     * @return list<CombatantState>
     */
    private function selectTargets(CombatantState $attacker, array $enemies, TechniqueDefinition $definition, CombatRules $rules): array
    {
        if ($enemies === []) {
            return [];
        }

        $config   = $definition->getConfig();
        $delivery = strtolower((string)($config['delivery'] ?? 'point'));

        return match ($delivery) {
            'aoe' => $enemies,
            // Backward compatibility for legacy configs persisted before cutover.
            'ray' => $this->selectLegacyRayTargets($enemies, (string)($config['piercing'] ?? 'first')),
            // Basic targeting model: single-target + AoE only.
            'single', 'projectile', 'point' => [$this->pickOne($enemies)],
            default => [$this->pickOne($enemies)],
        };
    }

    /**
     * @param list<CombatantState> $enemies
     *
     * @return list<CombatantState>
     */
    private function selectLegacyRayTargets(array $enemies, string $piercing): array
    {
        $piercing = strtolower(trim($piercing));

        // Legacy "ray + all" maps to current AoE behavior.
        if ($piercing === 'all') {
            return $enemies;
        }

        // Legacy "ray + first" maps to single-target.
        return [$this->pickOne($enemies)];
    }

    /**
     * @param list<CombatantState> $items
     */
    private function pickOne(array $items): CombatantState
    {
        $i = $this->rng->nextInt(0, count($items) - 1);
        return $items[$i];
    }

    /**
     * @param array<int,CombatantState> $states
     * @param list<string>              $log
     * @param list<int>                 $defeated
     */
    private function basicAttack(CombatantState $attacker, array $states, CombatRules $rules, array &$log, array &$defeated): void
    {
        $enemies = $this->enemyStates($attacker, $states, $rules);
        if ($enemies === []) {
            return;
        }

        $target = $this->pickOne($enemies);

        $damage            = $this->meleeDamage($attacker, $target);
        $target->currentHp = max(0, $target->currentHp - $damage);

        $log[] = sprintf('%s attacks %s for %d damage.', $attacker->character->getName(), $target->character->getName(), $damage);

        if ($target->isDefeated()) {
            $defeated[] = (int)$target->character->getId();
            $log[]      = sprintf('%s defeats %s.', $attacker->character->getName(), $target->character->getName());
        }
    }

    private function meleeDamage(CombatantState $attacker, CombatantState $defender): int
    {
        $atkEff = $this->transformations->effectiveAttributes($attacker->character->getCoreAttributes(), $attacker->transformationState);
        $defEff = $this->transformations->effectiveAttributes($defender->character->getCoreAttributes(), $defender->transformationState);

        return max(1, $atkEff->strength - intdiv($defEff->durability, 2));
    }

    private function shouldTransformNow(CombatantState $actor): bool
    {
        // Very simple heuristic for v1.
        $canSs = $actor->knowsTransformation(Transformation::SuperSaiyan);
        if (!$canSs) {
            return false;
        }

        // Transform early, rarely revert.
        if ($actor->transformationState->active === null) {
            return $this->rng->chance(0.25);
        }

        return $this->rng->chance(0.05);
    }

    /**
     * Mutates the in-combat transformation state only (does not persist to Character).
     *
     * @param list<string> $log
     */
    private function toggleTransformation(CombatantState $actor, array &$log): void
    {
        $state = $actor->transformationState;

        try {
            if ($state->active === null) {
                $actor->transformationState = $this->transformations->activate($state, Transformation::SuperSaiyan);
                $log[]                      = sprintf('%s transforms into %s.', $actor->character->getName(), Transformation::SuperSaiyan->value);
            } else {
                $actor->transformationState = $this->transformations->deactivate($state);
                $log[]                      = sprintf('%s reverts to normal.', $actor->character->getName());
            }
        } catch (\RuntimeException $e) {
            // Ignore (e.g. exhausted).
        }
    }

    /**
     * @param list<string> $log
     */
    private function startCharging(CombatantState $actor, TechniqueDefinition $definition, CharacterTechnique $knowledge, array &$log): void
    {
        $effectiveCost = $this->useCalc->effectiveKiCost($definition, $knowledge);
        if ($actor->currentKi < $effectiveCost) {
            return;
        }

        $config      = $definition->getConfig();
        $chargeTicks = max(0, (int)($config['chargeTicks'] ?? 0));

        $actor->startPreparingTechnique($definition->getCode(), $chargeTicks);
        $log[] = sprintf('%s starts charging %s.', $actor->character->getName(), $definition->getName());
    }

    /**
     * @param list<string> $log
     */
    private function advanceCharging(CombatantState $actor, array &$log, bool $skipContinueMessage = false): void
    {
        if ($actor->preparedPhase !== PreparedTechniquePhase::Charging) {
            return;
        }

        if ($actor->preparedTicksRemaining <= 0) {
            $actor->markPreparedReady();
            $log[] = sprintf('%s finishes charging %s.', $actor->character->getName(), (string)$actor->preparedTechniqueCode);
            return;
        }

        $actor->decrementPreparedTick();

        if ($actor->preparedTicksRemaining <= 0) {
            $actor->markPreparedReady();
            $log[] = sprintf('%s finishes charging %s.', $actor->character->getName(), (string)$actor->preparedTechniqueCode);
            return;
        }

        if (!$skipContinueMessage) {
            $log[] = sprintf('%s continues charging.', $actor->character->getName());
        }
    }

    /**
     * @param array<int,CombatantState> $states
     * @param list<string>              $log
     * @param list<int>                 $defeated
     */
    private function useTechniqueByCode(CombatantState $actor, string $techniqueCode, array $states, CombatRules $rules, array &$log, array &$defeated): void
    {
        foreach ($actor->techniques as $knowledge) {
            if (!$knowledge instanceof CharacterTechnique) {
                continue;
            }

            $def = $knowledge->getTechnique();
            if (strtolower($def->getCode()) === strtolower($techniqueCode)) {
                $this->useTechnique($actor, $def, $knowledge, $states, $rules, $log, $defeated);
                return;
            }
        }

        // Unknown technique: fallback.
        $this->basicAttack($actor, $states, $rules, $log, $defeated);
    }

    /**
     * @param array<int,CombatantState> $states
     * @param list<string>              $log
     * @param list<int>                 $defeated
     */
    private function useTechnique(
        CombatantState      $attacker,
        TechniqueDefinition $definition,
        CharacterTechnique  $knowledge,
        array               $states,
        CombatRules         $rules,
        array               &$log,
        array               &$defeated,
    ): void
    {
        $enemies = $this->enemyStates($attacker, $states, $rules);
        if ($enemies === []) {
            return;
        }

        $targets = $this->selectTargets($attacker, $enemies, $definition, $rules);

        $effectiveCost = $this->useCalc->effectiveKiCost($definition, $knowledge);
        $p             = $this->useCalc->successChance($definition, $knowledge);
        $success       = $this->rng->chance($p);

        $spent = 0;
        if ($success) {
            $spent = $effectiveCost;
        } else {
            $spent = (int)ceil($effectiveCost * $this->useCalc->failureKiCostMultiplier($definition));
        }

        if ($spent > 0 && $attacker->currentKi < $spent) {
            // Not enough Ki: fallback.
            $log[] = sprintf('%s tries to use %s, but lacks Ki.', $attacker->character->getName(), $definition->getName());
            $this->basicAttack($attacker, $states, $rules, $log, $defeated);
            return;
        }

        $attacker->currentKi = max(0, $attacker->currentKi - $spent);

        if (!$success) {
            $log[] = sprintf('%s fails to use %s.', $attacker->character->getName(), $definition->getName());
            return;
        }

        if ($targets === []) {
            $log[] = sprintf('%s uses %s, but it hits nothing.', $attacker->character->getName(), $definition->getName());
            $knowledge->incrementProficiency(1);
            return;
        }

        $anyDefeated = false;

        foreach ($targets as $target) {
            $damage            = $this->techniqueDamage($definition, $knowledge, $attacker, $target);
            $target->currentHp = max(0, $target->currentHp - $damage);

            $log[] = sprintf(
                '%s uses %s on %s for %d damage.',
                $attacker->character->getName(),
                $definition->getName(),
                $target->character->getName(),
                $damage,
            );

            if ($target->isDefeated()) {
                $anyDefeated = true;
                $defeated[]  = (int)$target->character->getId();
                $log[]       = sprintf('%s defeats %s.', $attacker->character->getName(), $target->character->getName());
            }
        }

        $knowledge->incrementProficiency(1);

        if ($anyDefeated) {
            // nothing else; caller loop will stop naturally.
        }
    }

    private function techniqueDamage(TechniqueDefinition $definition, CharacterTechnique $knowledge, CombatantState $attacker, CombatantState $defender): int
    {
        $config      = $definition->getConfig();
        $proficiency = $knowledge->getProficiency();

        $damageConfig = is_array($config['damage'] ?? null) ? $config['damage'] : [];
        $stat         = (string)($damageConfig['stat'] ?? 'kiControl');
        $statMult     = (float)($damageConfig['statMultiplier'] ?? 1.0);
        $base         = (int)($damageConfig['base'] ?? 0);
        $min          = max(0, (int)($damageConfig['min'] ?? 1));

        $mitStat    = (string)($damageConfig['mitigationStat'] ?? 'durability');
        $mitDivisor = max(1, (int)($damageConfig['mitigationDivisor'] ?? 2));

        $atkEff = $this->transformations->effectiveAttributes($attacker->character->getCoreAttributes(), $attacker->transformationState);
        $defEff = $this->transformations->effectiveAttributes($defender->character->getCoreAttributes(), $defender->transformationState);

        $attackerValue = $this->attributeValue($atkEff, $stat);
        $defenderValue = $this->attributeValue($defEff, $mitStat);

        $raw = $base + (int)floor($attackerValue * $statMult);

        $mult    = 1.0;
        $effects = $config['proficiencyEffects'] ?? null;
        if (is_array($effects) && isset($effects['damageMultiplier']['at0'], $effects['damageMultiplier']['at100'])) {
            /** @var array{at0:float|int,at100:float|int} $curve */
            $curve = $effects['damageMultiplier'];
            $mult  = max(0.0, (new \App\Game\Domain\Techniques\Execution\TechniqueMath())->curveAt($curve, $proficiency));
        }

        $raw = (int)floor($raw * $mult);

        $mitigation = intdiv(max(0, $defenderValue), $mitDivisor);
        $after      = $raw - $mitigation;

        return max($min, $after);
    }

    private function attributeValue(\App\Game\Domain\Stats\CoreAttributes $attributes, string $stat): int
    {
        return match (strtolower($stat)) {
            'strength' => $attributes->strength,
            'speed' => $attributes->speed,
            'endurance' => $attributes->endurance,
            'durability' => $attributes->durability,
            'kicapacity', 'ki_capacity' => $attributes->kiCapacity,
            'kicontrol', 'ki_control' => $attributes->kiControl,
            'kirecovery', 'ki_recovery' => $attributes->kiRecovery,
            'focus' => $attributes->focus,
            'discipline' => $attributes->discipline,
            'adaptability' => $attributes->adaptability,
            default => $attributes->kiControl,
        };
    }

    /**
     * @param array<int,CombatantState> $states
     *
     * @return list<CombatantState>
     */
    private function aliveStates(array $states): array
    {
        $alive = [];
        foreach ($states as $s) {
            if (!$s->isDefeated()) {
                $alive[] = $s;
            }
        }

        return $alive;
    }

    /**
     * @param array<int,CombatantState> $states
     *
     * @return list<CombatantState>
     */
    private function enemyStates(CombatantState $actor, array $states, CombatRules $rules): array
    {
        $enemies = [];
        foreach ($states as $s) {
            if ($s->isDefeated()) {
                continue;
            }
            if ($s === $actor) {
                continue;
            }

            if (!$rules->allowFriendlyFire && $s->teamId === $actor->teamId) {
                continue;
            }

            $enemies[] = $s;
        }

        return $enemies;
    }

    /**
     * @param array<int,CombatantState> $states
     */
    private function pickWinnerId(array $states, CombatRules $rules): int
    {
        $alive = $this->aliveStates($states);

        if ($alive === []) {
            // Everyone died somehow: pick by max HP, then lowest id.
            $bestId = null;
            $bestHp = null;

            foreach ($states as $id => $s) {
                if ($bestId === null || $bestHp === null || $s->currentHp > $bestHp || ($s->currentHp === $bestHp && $id < $bestId)) {
                    $bestId = $id;
                    $bestHp = $s->currentHp;
                }
            }

            if ($bestId === null) {
                throw new \LogicException('No combatants found for winner selection.');
            }

            return (int)$bestId;
        }

        if ($rules->allowFriendlyFire) {
            usort($alive, static fn(CombatantState $a, CombatantState $b): int => ((int)$a->character->getId()) <=> ((int)$b->character->getId()));
            return (int)$alive[0]->character->getId();
        }

        // Team victory: pick lowest-id member of surviving team.
        $team       = $alive[0]->teamId;
        $candidates = array_values(array_filter($alive, static fn(CombatantState $s): bool => $s->teamId === $team));
        usort($candidates, static fn(CombatantState $a, CombatantState $b): int => ((int)$a->character->getId()) <=> ((int)$b->character->getId()));

        return (int)$candidates[0]->character->getId();
    }
}
