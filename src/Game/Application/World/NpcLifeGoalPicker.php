<?php

namespace App\Game\Application\World;

use App\Game\Application\Goal\GoalCatalogProviderInterface;
use App\Game\Domain\Goal\GoalCatalog;

final class NpcLifeGoalPicker
{
    public function __construct(private readonly GoalCatalogProviderInterface $catalogProvider)
    {
    }

    public function pickForArchetype(string $npcArchetype): string
    {
        $npcArchetype = strtolower(trim($npcArchetype));
        if ($npcArchetype === '') {
            throw new \InvalidArgumentException('npcArchetype must not be empty.');
        }

        $catalog = $this->getCatalog();
        $pools   = $catalog->npcLifeGoals();
        $pool    = $pools[$npcArchetype] ?? null;

        if (!is_array($pool) || $pool === []) {
            throw new \RuntimeException(sprintf('No npc_life_goals configured for archetype: %s', $npcArchetype));
        }

        $total = 0;
        foreach ($pool as $item) {
            $weight = $item['weight'] ?? null;
            if (!is_int($weight) || $weight <= 0) {
                throw new \RuntimeException(sprintf('Invalid npc_life_goals weight for archetype: %s', $npcArchetype));
            }
            $total += $weight;
        }

        $roll = random_int(1, $total);
        foreach ($pool as $item) {
            $roll -= $item['weight'];
            if ($roll <= 0) {
                $code = $item['code'] ?? null;
                if (!is_string($code) || trim($code) === '') {
                    throw new \RuntimeException(sprintf('Invalid npc_life_goals code for archetype: %s', $npcArchetype));
                }

                // Ensure it is a known life goal.
                $catalog->lifeGoalPool($code);

                return $code;
            }
        }

        throw new \RuntimeException(sprintf('Failed to pick npc life goal for archetype: %s', $npcArchetype));
    }

    private function getCatalog(): GoalCatalog
    {
        return $this->catalogProvider->get();
    }
}
