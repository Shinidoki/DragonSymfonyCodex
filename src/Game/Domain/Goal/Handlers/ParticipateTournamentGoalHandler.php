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

final class ParticipateTournamentGoalHandler implements CurrentGoalHandlerInterface
{
    public function step(Character $character, World $world, array $data, GoalContext $context): GoalStepResult
    {
        $worldDay = $world->getCurrentDay();

        $timeoutDays = $data['search_timeout_days'] ?? 14;
        if (!is_int($timeoutDays) || $timeoutDays <= 0) {
            $timeoutDays = 14;
        }

        $expiresDay = $data['expires_day'] ?? null;
        if (!is_int($expiresDay) || $expiresDay < 0) {
            $expiresDay          = $worldDay + $timeoutDays;
            $data['expires_day'] = $expiresDay;
        }

        if ($worldDay > $expiresDay) {
            return new GoalStepResult(
                plan: new DailyPlan(DailyActivity::Rest),
                data: $data,
                completed: true,
            );
        }

        $x = $data['center_x'] ?? null;
        $y = $data['center_y'] ?? null;
        $resolveDay = $data['resolve_day'] ?? null;

        $hasTournamentTarget = is_int($x) && is_int($y) && $x >= 0 && $y >= 0 && is_int($resolveDay) && $resolveDay >= 0;

        // If the tournament has ended, the goal can complete immediately.
        if ($hasTournamentTarget && $worldDay > $resolveDay) {
            return new GoalStepResult(
                plan: new DailyPlan(DailyActivity::Rest),
                data: $data,
                completed: true,
            );
        }

        // If we have a target but missed registration and haven't arrived, fall back into "seek" mode.
        if ($hasTournamentTarget) {
            $registrationCloseDay = $data['registration_close_day'] ?? null;
            if (!is_int($registrationCloseDay)) {
                $announceDay = $data['announce_day'] ?? null;
                if (is_int($announceDay)) {
                    $registrationCloseDay = $announceDay + 1;
                }
            }

            if (is_int($registrationCloseDay) && $worldDay > $registrationCloseDay) {
                if ($character->getTileX() !== $x || $character->getTileY() !== $y) {
                    unset($data['center_x'], $data['center_y'], $data['resolve_day'], $data['announce_day'], $data['registration_close_day']);
                    $hasTournamentTarget = false;
                }
            }
        }

        // Follow the tournament target (travel there, then wait).
        if ($hasTournamentTarget) {
            if ($character->getTileX() === $x && $character->getTileY() === $y) {
                return new GoalStepResult(
                    plan: new DailyPlan(DailyActivity::Rest),
                    data: $data,
                    completed: false,
                );
            }

            return new GoalStepResult(
                plan: new DailyPlan(DailyActivity::Travel, travelTarget: new TileCoord($x, $y)),
                data: $data,
                completed: false,
            );
        }

        // No fixed tournament target: travel between settlements, and "latch" onto any joinable tournament
        // we come within attraction radius of.
        $candidate = $this->findJoinableTournamentNear($character, $worldDay, $context->events);
        if (is_array($candidate)) {
            $data['center_x']               = $candidate['center_x'];
            $data['center_y']               = $candidate['center_y'];
            $data['resolve_day']            = $candidate['resolve_day'];
            $data['announce_day']           = $candidate['announce_day'];
            $data['registration_close_day'] = $candidate['registration_close_day'];

            if ($character->getTileX() === $candidate['center_x'] && $character->getTileY() === $candidate['center_y']) {
                return new GoalStepResult(
                    plan: new DailyPlan(DailyActivity::Rest),
                    data: $data,
                    completed: false,
                );
            }

            return new GoalStepResult(
                plan: new DailyPlan(DailyActivity::Travel, travelTarget: new TileCoord($candidate['center_x'], $candidate['center_y'])),
                data: $data,
                completed: false,
            );
        }

        $next = $this->nextSettlementTarget($character, $context->settlementTiles, $data);
        if (!$next instanceof TileCoord) {
            return new GoalStepResult(
                plan: new DailyPlan(DailyActivity::Rest),
                data: $data,
                completed: false,
            );
        }

        return new GoalStepResult(
            plan: new DailyPlan(DailyActivity::Travel, travelTarget: $next),
            data: $data,
            completed: false,
        );
    }

    /**
     * @param list<CharacterEvent> $events
     *
     * @return array{center_x:int,center_y:int,resolve_day:int,announce_day:int,registration_close_day:int}|null
     */
    private function findJoinableTournamentNear(Character $character, int $worldDay, array $events): ?array
    {
        $best      = null;
        $bestScore = null;

        foreach ($events as $event) {
            if ($event->getType() !== 'tournament_announced') {
                continue;
            }

            $announceDay = $event->getDay();
            if ($announceDay >= $worldDay) {
                continue;
            }

            $data = $event->getData();
            if (!is_array($data)) {
                continue;
            }

            $cx         = $data['center_x'] ?? null;
            $cy         = $data['center_y'] ?? null;
            $radius     = $data['radius'] ?? null;
            $resolveDay = $data['resolve_day'] ?? null;

            if (!is_int($cx) || !is_int($cy) || $cx < 0 || $cy < 0) {
                continue;
            }
            if (!is_int($radius) || $radius < 0) {
                continue;
            }
            if (!is_int($resolveDay) || $resolveDay < $announceDay) {
                continue;
            }

            if ($worldDay > $resolveDay) {
                continue;
            }

            $registrationCloseDay = $announceDay + 1;
            if ($worldDay > $registrationCloseDay) {
                continue;
            }

            $dist = abs($character->getTileX() - $cx) + abs($character->getTileY() - $cy);
            if ($dist > $radius) {
                continue;
            }

            $availableSteps = $registrationCloseDay - $worldDay + 1;
            if ($availableSteps < 0 || $dist > $availableSteps) {
                continue;
            }

            $eventId = $event->getId() ?? PHP_INT_MAX;
            $score   = [$dist, -$resolveDay, $eventId];

            if ($best === null || $bestScore === null || $score < $bestScore) {
                $bestScore = $score;
                $best      = [
                    'center_x'               => $cx,
                    'center_y'               => $cy,
                    'resolve_day'            => $resolveDay,
                    'announce_day'           => $announceDay,
                    'registration_close_day' => $registrationCloseDay,
                ];
            }
        }

        return $best;
    }

    /**
     * Pick (and persist in $data) the next settlement target to travel to.
     *
     * @param list<TileCoord>     $settlements
     * @param array<string,mixed> $data
     */
    private function nextSettlementTarget(Character $character, array $settlements, array &$data): ?TileCoord
    {
        if ($settlements === []) {
            return null;
        }

        $tx = $data['search_target_x'] ?? null;
        $ty = $data['search_target_y'] ?? null;

        if (is_int($tx) && is_int($ty) && $tx >= 0 && $ty >= 0) {
            if ($character->getTileX() !== $tx || $character->getTileY() !== $ty) {
                return new TileCoord($tx, $ty);
            }

            $this->markVisited($data, $tx, $ty);
            unset($data['search_target_x'], $data['search_target_y']);
        }

        // If we're currently on a settlement tile, mark it visited.
        foreach ($settlements as $s) {
            if ($s->x === $character->getTileX() && $s->y === $character->getTileY()) {
                $this->markVisited($data, $s->x, $s->y);
                break;
            }
        }

        $visited = $data['visited_settlements'] ?? [];
        if (!is_array($visited)) {
            $visited = [];
        }

        $candidates = [];
        foreach ($settlements as $s) {
            if ($s->x === $character->getTileX() && $s->y === $character->getTileY()) {
                continue;
            }

            $key = sprintf('%d:%d', $s->x, $s->y);
            if (in_array($key, $visited, true)) {
                continue;
            }

            $candidates[] = $s;
        }

        if ($candidates === []) {
            // All visited: reset and allow repeats (but avoid staying in place).
            $data['visited_settlements'] = [];
            foreach ($settlements as $s) {
                if ($s->x === $character->getTileX() && $s->y === $character->getTileY()) {
                    continue;
                }
                $candidates[] = $s;
            }
        }

        if ($candidates === []) {
            return null;
        }

        $picked                  = $candidates[random_int(0, count($candidates) - 1)];
        $data['search_target_x'] = $picked->x;
        $data['search_target_y'] = $picked->y;

        return $picked;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function markVisited(array &$data, int $x, int $y): void
    {
        $visited = $data['visited_settlements'] ?? [];
        if (!is_array($visited)) {
            $visited = [];
        }

        $key = sprintf('%d:%d', $x, $y);
        if (!in_array($key, $visited, true)) {
            $visited[] = $key;
        }

        // Keep it bounded to avoid unbounded growth.
        if (count($visited) > 32) {
            $visited = array_slice($visited, -32);
        }

        $data['visited_settlements'] = $visited;
    }
}
