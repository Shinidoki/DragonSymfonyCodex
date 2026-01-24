<?php

namespace App\Game\Domain\Goal;

/**
 * @phpstan-type CurrentGoalPoolItem array{code:string,weight:int}
 * @phpstan-type LifeGoalDef array{label?:string,current_goal_pool:list<CurrentGoalPoolItem>}
 * @phpstan-type CurrentGoalDef array{label?:string,interruptible:bool,defaults?:array<string,mixed>,handler?:string,work_focus_target?:int}
 */
final readonly class GoalCatalog
{
    /**
     * @param array<string,LifeGoalDef>                         $lifeGoals
     * @param array<string,CurrentGoalDef>                      $currentGoals
     * @param array<string,list<array{code:string,weight:int}>> $npcLifeGoals
     * @param array<string,mixed>                               $eventRules
     */
    public function __construct(
        private array $lifeGoals,
        private array $currentGoals,
        private array $npcLifeGoals = [],
        private array $eventRules = [],
    )
    {
    }

    /**
     * @return array<string,LifeGoalDef>
     */
    public function lifeGoals(): array
    {
        return $this->lifeGoals;
    }

    /**
     * @return array<string,CurrentGoalDef>
     */
    public function currentGoals(): array
    {
        return $this->currentGoals;
    }

    /**
     * @return array<string,list<array{code:string,weight:int}>>
     */
    public function npcLifeGoals(): array
    {
        return $this->npcLifeGoals;
    }

    /**
     * @return array<string,mixed>
     */
    public function eventRules(): array
    {
        return $this->eventRules;
    }

    /**
     * @return list<array{code:string,weight:int}>
     */
    public function lifeGoalPool(string $lifeGoalCode): array
    {
        $def = $this->lifeGoals[$lifeGoalCode] ?? null;
        if (!is_array($def)) {
            throw new \InvalidArgumentException(sprintf('Unknown life goal: %s', $lifeGoalCode));
        }

        /** @var list<array{code:string,weight:int}> $pool */
        $pool = $def['current_goal_pool'] ?? null;
        if (!is_array($pool) || $pool === []) {
            throw new \InvalidArgumentException(sprintf('Life goal has no current goal pool: %s', $lifeGoalCode));
        }

        return $pool;
    }

    public function isCurrentGoalCompatible(string $lifeGoalCode, string $currentGoalCode): bool
    {
        foreach ($this->lifeGoalPool($lifeGoalCode) as $item) {
            if (($item['code'] ?? null) === $currentGoalCode) {
                return true;
            }
        }

        return false;
    }

    public function currentGoalInterruptible(string $currentGoalCode): bool
    {
        $def = $this->currentGoals[$currentGoalCode] ?? null;
        if (!is_array($def) || !array_key_exists('interruptible', $def)) {
            throw new \InvalidArgumentException(sprintf('Unknown current goal: %s', $currentGoalCode));
        }

        return (bool)$def['interruptible'];
    }

    /**
     * @return array<string,mixed>
     */
    public function currentGoalDefaults(string $currentGoalCode): array
    {
        $def = $this->currentGoals[$currentGoalCode] ?? null;
        if (!is_array($def)) {
            throw new \InvalidArgumentException(sprintf('Unknown current goal: %s', $currentGoalCode));
        }

        $defaults = $def['defaults'] ?? [];
        if ($defaults === null) {
            return [];
        }
        if (!is_array($defaults)) {
            throw new \InvalidArgumentException(sprintf('Current goal defaults must be a map: %s', $currentGoalCode));
        }

        /** @var array<string,mixed> $defaults */
        return $defaults;
    }

    public function currentGoalHandler(string $currentGoalCode): ?string
    {
        $def = $this->currentGoals[$currentGoalCode] ?? null;
        if (!is_array($def)) {
            throw new \InvalidArgumentException(sprintf('Unknown current goal: %s', $currentGoalCode));
        }

        $handler = $def['handler'] ?? null;
        if ($handler === null) {
            return null;
        }
        if (!is_string($handler) || trim($handler) === '') {
            throw new \InvalidArgumentException(sprintf('Current goal handler must be a class name: %s', $currentGoalCode));
        }

        return $handler;
    }

    public function currentGoalWorkFocusTarget(string $currentGoalCode): ?int
    {
        $def = $this->currentGoals[$currentGoalCode] ?? null;
        if (!is_array($def)) {
            throw new \InvalidArgumentException(sprintf('Unknown current goal: %s', $currentGoalCode));
        }

        $target = $def['work_focus_target'] ?? null;
        if ($target === null) {
            return null;
        }
        if (!is_int($target) || $target < 0 || $target > 100) {
            throw new \InvalidArgumentException(sprintf('Current goal work_focus_target must be an integer 0..100: %s', $currentGoalCode));
        }

        return $target;
    }
}
