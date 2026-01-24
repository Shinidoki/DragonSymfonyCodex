<?php

namespace App\Game\Domain\Goal;

final readonly class EventRuleMatcher
{
    /**
     * @return array<string,mixed>|null
     */
    public function match(GoalCatalog $catalog, string $eventType, ?string $lifeGoalCode): ?array
    {
        if ($lifeGoalCode === null || trim($lifeGoalCode) === '') {
            return null;
        }

        $eventRules = $catalog->eventRules();
        $forType    = $eventRules[$eventType] ?? null;
        if (!is_array($forType)) {
            return null;
        }

        $from = $forType['from'] ?? null;
        if (!is_array($from)) {
            return null;
        }

        $rule = $from[$lifeGoalCode] ?? null;
        if (!is_array($rule)) {
            return null;
        }

        /** @var array<string,mixed> $rule */
        return $rule;
    }
}

