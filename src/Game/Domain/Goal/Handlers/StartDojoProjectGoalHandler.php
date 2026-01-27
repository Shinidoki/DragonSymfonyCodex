<?php

namespace App\Game\Domain\Goal\Handlers;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\World;
use App\Game\Domain\Goal\CurrentGoalHandlerInterface;
use App\Game\Domain\Goal\GoalContext;
use App\Game\Domain\Goal\GoalStepResult;
use App\Game\Domain\Map\TileCoord;
use App\Game\Domain\Npc\DailyActivity;
use App\Game\Domain\Npc\DailyPlan;

final class StartDojoProjectGoalHandler implements CurrentGoalHandlerInterface
{
    private const BUILDING_CODE = 'dojo';
    private const MAX_LEVEL     = 3;

    public function step(Character $character, World $world, array $data, GoalContext $context): GoalStepResult
    {
        $sx = $character->getEmploymentSettlementX();
        $sy = $character->getEmploymentSettlementY();

        if (!is_int($sx) || !is_int($sy) || $sx < 0 || $sy < 0) {
            return new GoalStepResult(
                plan: new DailyPlan(DailyActivity::Rest),
                data: $data,
                completed: true,
            );
        }

        if ($character->getTileX() !== $sx || $character->getTileY() !== $sy) {
            return new GoalStepResult(
                plan: new DailyPlan(DailyActivity::Travel, travelTarget: new TileCoord($sx, $sy)),
                data: $data,
                completed: false,
            );
        }

        $key = sprintf('%d:%d', $sx, $sy);

        $active = $context->activeSettlementProjectsByCoord[$key] ?? null;
        if (is_array($active)) {
            return new GoalStepResult(
                plan: new DailyPlan(DailyActivity::Rest),
                data: $data,
                completed: true,
            );
        }

        $buildings = $context->settlementBuildingsByCoord[$key] ?? [];
        $current   = $buildings[self::BUILDING_CODE] ?? 0;
        if (!is_int($current) || $current < 0) {
            $current = 0;
        }

        if ($current >= self::MAX_LEVEL) {
            return new GoalStepResult(
                plan: new DailyPlan(DailyActivity::Rest),
                data: $data,
                completed: true,
            );
        }

        $event = new CharacterEvent(
            world: $world,
            character: null,
            type: 'settlement_project_start_requested',
            day: $world->getCurrentDay(),
            data: [
                'settlement_x'  => $sx,
                'settlement_y'  => $sy,
                'building_code' => self::BUILDING_CODE,
                'target_level'  => $current + 1,
            ],
        );

        return new GoalStepResult(
            plan: new DailyPlan(DailyActivity::Rest),
            data: $data,
            completed: true,
            events: [$event],
        );
    }
}

