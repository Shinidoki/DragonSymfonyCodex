<?php

namespace App\Game\Domain\Goal;

use Symfony\Component\Yaml\Yaml;

final class GoalCatalogLoader
{
    public function loadFromFile(string $path): GoalCatalog
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException(sprintf('Goals YAML not found: %s', $path));
        }

        $raw = Yaml::parseFile($path);
        if (!is_array($raw)) {
            throw new \InvalidArgumentException('Goals YAML must contain a mapping at the root.');
        }

        $lifeGoals    = $raw['life_goals'] ?? null;
        $currentGoals = $raw['current_goals'] ?? null;

        if (!is_array($lifeGoals) || $lifeGoals === []) {
            throw new \InvalidArgumentException('Goals YAML must define life_goals.');
        }
        if (!is_array($currentGoals) || $currentGoals === []) {
            throw new \InvalidArgumentException('Goals YAML must define current_goals.');
        }

        $npcLifeGoals = $raw['npc_life_goals'] ?? [];
        if ($npcLifeGoals === null) {
            $npcLifeGoals = [];
        }
        if (!is_array($npcLifeGoals)) {
            throw new \InvalidArgumentException('Goals YAML npc_life_goals must be a mapping when provided.');
        }

        $eventRules = $raw['event_rules'] ?? [];
        if ($eventRules === null) {
            $eventRules = [];
        }
        if (!is_array($eventRules)) {
            throw new \InvalidArgumentException('Goals YAML event_rules must be a mapping when provided.');
        }

        /** @var array<string,mixed> $eventRules */
        foreach ($eventRules as $eventType => $eventDef) {
            if (!is_string($eventType) || trim($eventType) === '') {
                throw new \InvalidArgumentException('event_rules keys must be non-empty strings.');
            }
            if (!is_array($eventDef)) {
                throw new \InvalidArgumentException(sprintf('event_rule %s must be a mapping.', $eventType));
            }

            $from = $eventDef['from'] ?? null;
            if ($from === null) {
                continue;
            }
            if (!is_array($from)) {
                throw new \InvalidArgumentException(sprintf('event_rule %s from must be a mapping when provided.', $eventType));
            }

            foreach ($from as $lifeGoalCode => $rule) {
                if (!is_string($lifeGoalCode) || trim($lifeGoalCode) === '') {
                    throw new \InvalidArgumentException(sprintf('event_rule %s from keys must be non-empty strings.', $eventType));
                }
                if (!is_array($rule)) {
                    throw new \InvalidArgumentException(sprintf('event_rule %s from.%s must be a mapping.', $eventType, $lifeGoalCode));
                }

                if (array_key_exists('chance', $rule) && !is_float($rule['chance']) && !is_int($rule['chance'])) {
                    throw new \InvalidArgumentException(sprintf('event_rule %s from.%s chance must be a number.', $eventType, $lifeGoalCode));
                }

                if (array_key_exists('transitions', $rule)) {
                    $transitions = $rule['transitions'];
                    if (!is_array($transitions)) {
                        throw new \InvalidArgumentException(sprintf('event_rule %s from.%s transitions must be a list.', $eventType, $lifeGoalCode));
                    }
                    foreach ($transitions as $i => $t) {
                        if (!is_array($t)) {
                            throw new \InvalidArgumentException(sprintf('event_rule %s from.%s transitions[%d] must be a mapping.', $eventType, $lifeGoalCode, $i));
                        }
                        $to     = $t['to'] ?? null;
                        $weight = $t['weight'] ?? null;
                        if (!is_string($to) || trim($to) === '') {
                            throw new \InvalidArgumentException(sprintf('event_rule %s from.%s transitions[%d].to must be a non-empty string.', $eventType, $lifeGoalCode, $i));
                        }
                        if (!is_int($weight) || $weight <= 0) {
                            throw new \InvalidArgumentException(sprintf('event_rule %s from.%s transitions[%d].weight must be a positive integer.', $eventType, $lifeGoalCode, $i));
                        }
                    }
                }

                if (array_key_exists('clear_current_goal', $rule) && $rule['clear_current_goal'] !== null && !is_bool($rule['clear_current_goal'])) {
                    throw new \InvalidArgumentException(sprintf('event_rule %s from.%s clear_current_goal must be a boolean.', $eventType, $lifeGoalCode));
                }

                if (array_key_exists('set_current_goal', $rule) && $rule['set_current_goal'] !== null) {
                    $set = $rule['set_current_goal'];
                    if (!is_array($set)) {
                        throw new \InvalidArgumentException(sprintf('event_rule %s from.%s set_current_goal must be a mapping.', $eventType, $lifeGoalCode));
                    }
                    $code = $set['code'] ?? null;
                    if (!is_string($code) || trim($code) === '') {
                        throw new \InvalidArgumentException(sprintf('event_rule %s from.%s set_current_goal.code must be a non-empty string.', $eventType, $lifeGoalCode));
                    }
                    if (array_key_exists('chance', $set) && $set['chance'] !== null && !is_float($set['chance']) && !is_int($set['chance'])) {
                        throw new \InvalidArgumentException(sprintf('event_rule %s from.%s set_current_goal.chance must be a number when provided.', $eventType, $lifeGoalCode));
                    }
                    if (array_key_exists('data', $set) && $set['data'] !== null && !is_array($set['data'])) {
                        throw new \InvalidArgumentException(sprintf('event_rule %s from.%s set_current_goal.data must be a mapping when provided.', $eventType, $lifeGoalCode));
                    }
                }
            }
        }

        /** @var array<string,array<string,mixed>> $lifeGoals */
        foreach ($lifeGoals as $lifeGoalCode => $def) {
            if (!is_string($lifeGoalCode) || $lifeGoalCode === '') {
                throw new \InvalidArgumentException('life_goals keys must be non-empty strings.');
            }
            if (!is_array($def)) {
                throw new \InvalidArgumentException(sprintf('life_goal %s must be a mapping.', $lifeGoalCode));
            }

            $pool = $def['current_goal_pool'] ?? null;
            if (!is_array($pool) || $pool === []) {
                throw new \InvalidArgumentException(sprintf('life_goal %s must define a non-empty current_goal_pool.', $lifeGoalCode));
            }
            foreach ($pool as $i => $item) {
                if (!is_array($item)) {
                    throw new \InvalidArgumentException(sprintf('life_goal %s current_goal_pool[%d] must be a mapping.', $lifeGoalCode, $i));
                }
                $code   = $item['code'] ?? null;
                $weight = $item['weight'] ?? null;
                if (!is_string($code) || trim($code) === '') {
                    throw new \InvalidArgumentException(sprintf('life_goal %s current_goal_pool[%d].code must be a non-empty string.', $lifeGoalCode, $i));
                }
                if (!is_int($weight) || $weight <= 0) {
                    throw new \InvalidArgumentException(sprintf('life_goal %s current_goal_pool[%d].weight must be a positive integer.', $lifeGoalCode, $i));
                }
            }
        }

        /** @var array<string,array<string,mixed>> $currentGoals */
        foreach ($currentGoals as $currentGoalCode => $def) {
            if (!is_string($currentGoalCode) || $currentGoalCode === '') {
                throw new \InvalidArgumentException('current_goals keys must be non-empty strings.');
            }
            if (!is_array($def)) {
                throw new \InvalidArgumentException(sprintf('current_goal %s must be a mapping.', $currentGoalCode));
            }
            if (!array_key_exists('interruptible', $def) || !is_bool($def['interruptible'])) {
                throw new \InvalidArgumentException(sprintf('current_goal %s must define interruptible: true|false.', $currentGoalCode));
            }
            if (array_key_exists('defaults', $def) && $def['defaults'] !== null && !is_array($def['defaults'])) {
                throw new \InvalidArgumentException(sprintf('current_goal %s defaults must be a mapping when provided.', $currentGoalCode));
            }
            if (array_key_exists('work_focus_target', $def) && $def['work_focus_target'] !== null) {
                $target = $def['work_focus_target'];
                if (!is_int($target) || $target < 0 || $target > 100) {
                    throw new \InvalidArgumentException(sprintf('current_goal %s work_focus_target must be an integer 0..100 when provided.', $currentGoalCode));
                }
            }
        }

        // Ensure referenced current goals exist.
        /** @var array<string,array<string,mixed>> $lifeGoals */
        foreach ($lifeGoals as $lifeGoalCode => $def) {
            /** @var list<array{code:string,weight:int}> $pool */
            $pool = $def['current_goal_pool'];
            foreach ($pool as $item) {
                $code = $item['code'];
                if (!array_key_exists($code, $currentGoals)) {
                    throw new \InvalidArgumentException(sprintf('life_goal %s references unknown current_goal %s.', $lifeGoalCode, $code));
                }
            }
        }

        /** @var array<string,mixed> $eventRules */
        return new GoalCatalog(
            lifeGoals: $lifeGoals,
            currentGoals: $currentGoals,
            npcLifeGoals: $npcLifeGoals,
            eventRules: $eventRules,
        );
    }
}
