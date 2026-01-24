<?php

namespace App\Game\Domain\Npc;

use App\Entity\Character;
use App\Entity\NpcProfile;
use App\Game\Domain\Map\TileCoord;

final class DailyPlanner
{
    /**
     * @param list<TileCoord> $dojoTiles
     */
    public function planFor(Character $character, ?NpcProfile $npcProfile = null, array $dojoTiles = []): DailyPlan
    {
        if ($character->hasTravelTarget()) {
            return new DailyPlan(DailyActivity::Travel);
        }

        if (!$npcProfile instanceof NpcProfile) {
            return new DailyPlan(DailyActivity::Train);
        }

        return match ($npcProfile->getArchetype()) {
            NpcArchetype::Civilian => new DailyPlan(DailyActivity::Rest),
            NpcArchetype::Fighter => $this->planForFighter($character, $dojoTiles),
            NpcArchetype::Wanderer => $this->planForWanderer($character, $npcProfile),
        };
    }

    /**
     * @param list<TileCoord> $dojoTiles
     */
    private function planForFighter(Character $character, array $dojoTiles): DailyPlan
    {
        $current = new TileCoord($character->getTileX(), $character->getTileY());

        if (count($dojoTiles) === 0) {
            return new DailyPlan(DailyActivity::Train);
        }

        foreach ($dojoTiles as $dojo) {
            if ($dojo->x === $current->x && $dojo->y === $current->y) {
                return new DailyPlan(DailyActivity::Train);
            }
        }

        $target = $this->nearestDojo($current, $dojoTiles);
        if (!$target instanceof TileCoord) {
            return new DailyPlan(DailyActivity::Train);
        }

        return new DailyPlan(DailyActivity::Travel, travelTarget: $target);
    }

    private function planForWanderer(Character $character, NpcProfile $npcProfile): DailyPlan
    {
        $world  = $character->getWorld();
        $width  = $world->getWidth();
        $height = $world->getHeight();

        if ($width <= 0 || $height <= 0) {
            return new DailyPlan(DailyActivity::Train);
        }

        $sequence = $npcProfile->getWanderSequence() + 1;

        $hash = hash('sha256', sprintf('%s:wander:%s:%d', $world->getSeed(), $character->getName(), $sequence));
        $x    = (int)hexdec(substr($hash, 0, 8)) % $width;
        $y    = (int)hexdec(substr($hash, 8, 8)) % $height;

        if ($x === $character->getTileX() && $y === $character->getTileY()) {
            $x = ($x + 1) % $width;
        }

        return new DailyPlan(DailyActivity::Travel, travelTarget: new TileCoord($x, $y));
    }

    /**
     * @param list<TileCoord> $dojoTiles
     */
    private function nearestDojo(TileCoord $from, array $dojoTiles): ?TileCoord
    {
        $best     = null;
        $bestDist = null;

        foreach ($dojoTiles as $candidate) {
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
