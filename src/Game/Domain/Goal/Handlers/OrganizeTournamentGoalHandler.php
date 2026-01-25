<?php

namespace App\Game\Domain\Goal\Handlers;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\Settlement;
use App\Entity\World;
use App\Game\Domain\Economy\EconomyCatalog;
use App\Game\Domain\Goal\CurrentGoalHandlerInterface;
use App\Game\Domain\Goal\GoalContext;
use App\Game\Domain\Goal\GoalStepResult;
use App\Game\Domain\Map\TileCoord;
use App\Game\Domain\Npc\DailyActivity;
use App\Game\Domain\Npc\DailyPlan;

final class OrganizeTournamentGoalHandler implements CurrentGoalHandlerInterface
{
    public function step(Character $character, World $world, array $data, GoalContext $context): GoalStepResult
    {
        if (!$context->economyCatalog instanceof EconomyCatalog) {
            return new GoalStepResult(
                plan: new DailyPlan(DailyActivity::Rest),
                data: $data,
                completed: false,
            );
        }

        $settlements = $context->settlementTiles;
        if ($settlements === []) {
            return new GoalStepResult(
                plan: new DailyPlan(DailyActivity::Rest),
                data: $data,
                completed: false,
            );
        }

        $width  = $world->getWidth();
        $height = $world->getHeight();

        $targetX = $data['target_x'] ?? null;
        $targetY = $data['target_y'] ?? null;

        $target = null;
        if (is_int($targetX) && is_int($targetY)) {
            if ($targetX >= 0 && $targetY >= 0 && ($width <= 0 || $targetX < $width) && ($height <= 0 || $targetY < $height)) {
                foreach ($settlements as $s) {
                    if ($s->x === $targetX && $s->y === $targetY) {
                        $target = $s;
                        break;
                    }
                }
            }
        }

        if (!$target instanceof TileCoord) {
            $target = $this->nearestSettlement(new TileCoord($character->getTileX(), $character->getTileY()), $settlements);
        }

        if (!$target instanceof TileCoord) {
            return new GoalStepResult(
                plan: new DailyPlan(DailyActivity::Rest),
                data: $data,
                completed: false,
            );
        }

        $data['target_x'] = $target->x;
        $data['target_y'] = $target->y;

        if ($character->getTileX() !== $target->x || $character->getTileY() !== $target->y) {
            return new GoalStepResult(
                plan: new DailyPlan(DailyActivity::Travel, travelTarget: $target),
                data: $data,
                completed: false,
            );
        }

        $settlement = $context->settlementsByCoord[sprintf('%d:%d', $target->x, $target->y)] ?? null;
        if (!$settlement instanceof Settlement) {
            return new GoalStepResult(
                plan: new DailyPlan(DailyActivity::Rest),
                data: $data,
                completed: false,
            );
        }

        $catalog  = $context->economyCatalog;
        $minSpend = $catalog->tournamentMinSpend();
        $maxFrac  = $catalog->tournamentMaxSpendFractionOfTreasury();

        if ($minSpend <= 0 || $maxFrac <= 0.0) {
            return new GoalStepResult(
                plan: new DailyPlan(DailyActivity::Rest),
                data: $data,
                completed: false,
            );
        }

        $treasury = $settlement->getTreasury();
        $maxSpend = (int)floor($treasury * $maxFrac);

        if ($treasury < $minSpend || $maxSpend < $minSpend) {
            return new GoalStepResult(
                plan: new DailyPlan(DailyActivity::Rest),
                data: $data,
                completed: false,
            );
        }

        $spend = $data['spend'] ?? null;
        if (!is_int($spend) || $spend < $minSpend || $spend > $maxSpend) {
            $spend = random_int($minSpend, $maxSpend);
        }

        $durationDays = $catalog->tournamentDurationDays();
        if ($durationDays < 1) {
            $durationDays = 1;
        }

        $radius   = $catalog->tournamentRadiusBase();
        $perSpend = $catalog->tournamentRadiusPerSpend();
        if ($perSpend > 0) {
            $radius += intdiv($spend, $perSpend);
        }
        $radius = min($radius, $catalog->tournamentRadiusMax());

        $prizePool = (int)floor($spend * $catalog->tournamentPrizePoolFraction());
        if ($prizePool < 0) {
            $prizePool = 0;
        }
        if ($prizePool > $spend) {
            $prizePool = $spend;
        }

        $prize1 = (int)floor($prizePool * 0.5);
        $prize2 = (int)floor($prizePool * 0.3);
        $prize3 = $prizePool - $prize1 - $prize2;

        $fameGain = $catalog->tournamentFameBase();
        $famePer  = $catalog->tournamentFamePerSpend();
        if ($famePer > 0) {
            $fameGain += intdiv($spend, $famePer);
        }

        $prosGain = $catalog->tournamentProsperityBase();
        $prosPer  = $catalog->tournamentProsperityPerSpend();
        if ($prosPer > 0) {
            $prosGain += intdiv($spend, $prosPer);
        }

        $settlement->addToTreasury(-$spend);
        $settlement->addFame($fameGain);
        $settlement->setProsperity($settlement->getProsperity() + $prosGain);

        $event = new CharacterEvent(
            world: $world,
            character: null,
            type: 'tournament_announced',
            day: $world->getCurrentDay(),
            data: [
                'center_x'        => $settlement->getX(),
                'center_y'        => $settlement->getY(),
                'radius'          => $radius,
                'spend'           => $spend,
                'prize_pool'      => $prizePool,
                'prize_1'         => $prize1,
                'prize_2'         => $prize2,
                'prize_3'         => $prize3,
                'fame_gain'       => $fameGain,
                'prosperity_gain' => $prosGain,
                'resolve_day'     => $world->getCurrentDay() + $durationDays,
            ],
        );

        return new GoalStepResult(
            plan: new DailyPlan(DailyActivity::Rest),
            data: $data,
            completed: true,
            events: [$event],
        );
    }

    /**
     * @param list<TileCoord> $settlements
     */
    private function nearestSettlement(TileCoord $from, array $settlements): ?TileCoord
    {
        $best     = null;
        $bestDist = null;

        foreach ($settlements as $candidate) {
            $dist = abs($candidate->x - $from->x) + abs($candidate->y - $from->y);

            if ($best === null) {
                $best     = $candidate;
                $bestDist = $dist;
                continue;
            }

            if ($dist < $bestDist) {
                $best     = $candidate;
                $bestDist = $dist;
                continue;
            }

            if ($dist === $bestDist) {
                if ($candidate->x < $best->x || ($candidate->x === $best->x && $candidate->y < $best->y)) {
                    $best = $candidate;
                }
            }
        }

        return $best;
    }
}
