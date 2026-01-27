<?php

namespace App\Game\Domain\Goal;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\CharacterGoal;

final readonly class CharacterGoalResolver
{
    public function __construct(private EventRuleMatcher $matcher = new EventRuleMatcher())
    {
    }

    /**
     * Resolve goals for a character at the start of a day.
     *
     * Rules:
     * - At most one life-goal change per day.
     * - Consume all events by increasing lastProcessedEventId through the list.
     * - Broadcast events may include a Manhattan radius filter in event data: center_x, center_y, radius.
     *
     * @param list<CharacterEvent> $events Assumed (but not required) to be sorted by id ASC.
     */
    public function resolveForDay(
        Character     $character,
        CharacterGoal $goal,
        GoalCatalog   $catalog,
        int           $worldDay,
        array         $events,
    ): void
    {
        if ($worldDay < 0) {
            throw new \InvalidArgumentException('worldDay must be >= 0.');
        }

        if ($goal->getLastResolvedDay() === $worldDay) {
            return;
        }

        $events = $this->sortedEvents($events);

        $lifeGoalChangedToday = false;
        $lastProcessed        = $goal->getLastProcessedEventId();

        foreach ($events as $event) {
            $eventId = $event->getId();
            if ($eventId === null) {
                throw new \RuntimeException('CharacterEvent must have an id to be processed.');
            }
            if ($eventId <= $lastProcessed) {
                continue;
            }
            if ($event->getDay() >= $worldDay) {
                continue;
            }

            $target = $event->getCharacter();
            if ($target instanceof Character) {
                $targetId    = $target->getId();
                $characterId = $character->getId();

                if ($targetId !== null && $characterId !== null) {
                    if ((int)$targetId !== (int)$characterId) {
                        $lastProcessed = $eventId;
                        $goal->setLastProcessedEventId($lastProcessed);
                        continue;
                    }
                } elseif ($target !== $character) {
                    $lastProcessed = $eventId;
                    $goal->setLastProcessedEventId($lastProcessed);
                    continue;
                }
            }

            $ruleApplies = $this->broadcastRadiusAllowsEvent($character, $event);
            if ($ruleApplies) {
                $rule = $this->matcher->match($catalog, $event->getType(), $goal->getLifeGoalCode());
                if ($rule !== null) {
                    if (!$lifeGoalChangedToday) {
                        $didChange = $this->maybeChangeLifeGoal($goal, $rule, $catalog);
                        if ($didChange) {
                            $lifeGoalChangedToday = true;
                        }
                    }

                    $this->applyCurrentGoalOverrides($goal, $rule, $catalog, $event);
                }
            }

            $lastProcessed = $eventId;
            $goal->setLastProcessedEventId($lastProcessed);
        }

        $this->enforceCompatibility($character, $goal, $catalog);
        $this->ensureCurrentGoal($character, $goal, $catalog);

        $goal->setLastResolvedDay($worldDay);
    }

    /**
     * @param list<CharacterEvent> $events
     *
     * @return list<CharacterEvent>
     */
    private function sortedEvents(array $events): array
    {
        usort($events, static function (CharacterEvent $a, CharacterEvent $b): int {
            $ai = $a->getId();
            $bi = $b->getId();

            if ($ai === null && $bi === null) {
                return 0;
            }
            if ($ai === null) {
                return 1;
            }
            if ($bi === null) {
                return -1;
            }

            return $ai <=> $bi;
        });

        return $events;
    }

    private function maybeChangeLifeGoal(CharacterGoal $goal, array $rule, GoalCatalog $catalog): bool
    {
        $transitions = $rule['transitions'] ?? null;
        if (!is_array($transitions) || $transitions === []) {
            return false;
        }

        $chance = $rule['chance'] ?? null;
        if (!is_float($chance) && !is_int($chance)) {
            return false;
        }
        $chance = (float)$chance;
        if ($chance <= 0) {
            return false;
        }
        if ($chance > 1) {
            $chance = 1.0;
        }

        $roll = random_int(0, 1_000_000) / 1_000_000;
        if ($roll > $chance) {
            return false;
        }

        $picked = $this->pickWeightedTransition($transitions);
        if ($picked === null) {
            return false;
        }

        $newLifeGoal = $picked['to'];
        $catalog->lifeGoalPool($newLifeGoal); // validate

        $goal->setLifeGoalCode($newLifeGoal);

        return true;
    }

    /**
     * @param array<int,mixed> $transitions
     *
     * @return array{to:string}|null
     */
    private function pickWeightedTransition(array $transitions): ?array
    {
        $total = 0;
        $items = [];

        foreach ($transitions as $t) {
            if (!is_array($t)) {
                continue;
            }
            $to     = $t['to'] ?? null;
            $weight = $t['weight'] ?? null;
            if (!is_string($to) || trim($to) === '') {
                continue;
            }
            if (!is_int($weight) || $weight <= 0) {
                continue;
            }

            $items[] = ['to' => $to, 'weight' => $weight];
            $total   += $weight;
        }

        if ($total <= 0) {
            return null;
        }

        $roll = random_int(1, $total);
        foreach ($items as $item) {
            $roll -= $item['weight'];
            if ($roll <= 0) {
                return ['to' => $item['to']];
            }
        }

        return null;
    }

    private function applyCurrentGoalOverrides(CharacterGoal $goal, array $rule, GoalCatalog $catalog, CharacterEvent $event): void
    {
        $currentGoalCode = $goal->getCurrentGoalCode();

        // "Low money" is meant as a fallback when the character is idle/available.
        // It should not interrupt active, in-progress goals (e.g. tournaments) on the same resolution pass.
        if (
            ($event->getType() === 'money_low_employed' || $event->getType() === 'money_low_unemployed')
            && $currentGoalCode !== null
            && !$goal->isCurrentGoalComplete()
        ) {
            return;
        }

        $canInterrupt    = $currentGoalCode === null || $goal->isCurrentGoalComplete();
        if (!$canInterrupt && $currentGoalCode !== null) {
            $canInterrupt = $catalog->currentGoalInterruptible($currentGoalCode);
        }

        $clear = $rule['clear_current_goal'] ?? false;
        if ($clear === true && $canInterrupt) {
            $goal->setCurrentGoalCode(null);
            $goal->setCurrentGoalData(null);
            $goal->setCurrentGoalComplete(false);
        }

        $set = $rule['set_current_goal'] ?? null;
        if (is_array($set) && $canInterrupt) {
            $chanceVal = $set['chance'] ?? null;
            if (is_float($chanceVal) || is_int($chanceVal)) {
                $chance = (float)$chanceVal;
                if ($chance <= 0.0) {
                    return;
                }
                if ($chance > 1.0) {
                    $chance = 1.0;
                }

                $roll = random_int(0, 1_000_000) / 1_000_000;
                if ($roll > $chance) {
                    return;
                }
            }

            $code = $set['code'] ?? null;
            if (!is_string($code) || trim($code) === '') {
                return;
            }

            $lifeGoal = $goal->getLifeGoalCode();
            if (!$lifeGoal || !$catalog->isCurrentGoalCompatible($lifeGoal, $code)) {
                return;
            }

            $data      = $catalog->currentGoalDefaults($code);
            $eventData = $event->getData() ?? [];
            if (is_array($eventData)) {
                $data = array_merge($data, $eventData);
            }
            $ruleData = $set['data'] ?? null;
            if (is_array($ruleData)) {
                $data = array_merge($data, $ruleData);
            }

            $goal->setCurrentGoalCode($code);
            $goal->setCurrentGoalData($data);
            $goal->setCurrentGoalComplete(false);
        }
    }

    private function enforceCompatibility(Character $character, CharacterGoal $goal, GoalCatalog $catalog): void
    {
        $lifeGoal = $this->effectiveLifeGoalCode($character, $goal, $catalog);
        $currentGoal = $goal->getCurrentGoalCode();

        if ($lifeGoal === null || $currentGoal === null) {
            return;
        }

        if (!$catalog->isCurrentGoalCompatible($lifeGoal, $currentGoal)) {
            $goal->setCurrentGoalCode(null);
            $goal->setCurrentGoalData(null);
            $goal->setCurrentGoalComplete(false);
        }
    }

    private function ensureCurrentGoal(Character $character, CharacterGoal $goal, GoalCatalog $catalog): void
    {
        if ($goal->getCurrentGoalCode() !== null && !$goal->isCurrentGoalComplete()) {
            return;
        }

        $lifeGoal = $this->effectiveLifeGoalCode($character, $goal, $catalog);
        if ($lifeGoal === null) {
            return;
        }

        $pool  = $catalog->lifeGoalPool($lifeGoal);
        $total = 0;
        foreach ($pool as $item) {
            $total += $item['weight'];
        }
        if ($total <= 0) {
            return;
        }

        $roll = random_int(1, $total);
        foreach ($pool as $item) {
            $roll -= $item['weight'];
            if ($roll <= 0) {
                $code = $item['code'];
                $goal->setCurrentGoalCode($code);
                $goal->setCurrentGoalData($catalog->currentGoalDefaults($code));
                $goal->setCurrentGoalComplete(false);
                return;
            }
        }
    }

    private function effectiveLifeGoalCode(Character $character, CharacterGoal $goal, GoalCatalog $catalog): ?string
    {
        $currentGoalCode   = $goal->getCurrentGoalCode();
        $mayPickMayorGoals = $currentGoalCode === null || $goal->isCurrentGoalComplete();

        if ($mayPickMayorGoals && $character->isEmployed() && $character->getEmploymentJobCode() === 'mayor') {
            if (array_key_exists('leader.lead_settlement', $catalog->lifeGoals())) {
                return 'leader.lead_settlement';
            }
        }

        return $goal->getLifeGoalCode();
    }

    private function broadcastRadiusAllowsEvent(Character $character, CharacterEvent $event): bool
    {
        if ($event->getCharacter() instanceof Character) {
            return true;
        }

        $data = $event->getData();
        if (!is_array($data)) {
            return true;
        }

        if (!array_key_exists('center_x', $data) || !array_key_exists('center_y', $data) || !array_key_exists('radius', $data)) {
            return true;
        }

        $cx = $data['center_x'];
        $cy = $data['center_y'];
        $r  = $data['radius'];

        if (!is_int($cx) || !is_int($cy) || !is_int($r) || $r < 0) {
            return true;
        }

        $dist = abs($character->getTileX() - $cx) + abs($character->getTileY() - $cy);

        return $dist <= $r;
    }
}
