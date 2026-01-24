<?php

namespace App\Game\Domain\Techniques\Targeting;

use App\Entity\LocalActor;
use App\Game\Domain\LocalMap\AimMode;
use App\Game\Domain\LocalMap\Direction;

final class LocalTargetSelector
{
    /**
     * @param list<LocalActor> $actors
     *
     * @return list<LocalActor>
     */
    public function selectTargets(
        LocalActor $attacker,
        array      $actors,
        AimMode    $aimMode,
        ?Direction $direction,
        ?int       $targetX,
        ?int       $targetY,
        int        $range,
        string     $delivery,
        ?string    $piercing,
        ?int       $aoeRadius,
    ): array
    {
        $range    = max(0, $range);
        $delivery = strtolower($delivery);

        return match ($delivery) {
            'projectile' => $this->selectProjectile($attacker, $actors, $aimMode, $direction, $targetX, $targetY, $range),
            'ray' => $this->selectRay($attacker, $actors, $aimMode, $direction, $range, $piercing),
            'aoe' => $this->selectAoe($attacker, $actors, $aimMode, $targetX, $targetY, $range, $aoeRadius),
            'point' => $this->selectPoint($attacker, $actors, $aimMode, $targetX, $targetY, $range),
            default => [],
        };
    }

    /**
     * @param list<LocalActor> $actors
     *
     * @return list<LocalActor>
     */
    private function selectProjectile(
        LocalActor $attacker,
        array      $actors,
        AimMode    $aimMode,
        ?Direction $direction,
        ?int       $targetX,
        ?int       $targetY,
        int        $range,
    ): array
    {
        if ($aimMode === AimMode::Direction) {
            if ($direction === null) {
                return [];
            }

            $dxdy = $this->dirDelta($direction);

            for ($i = 1; $i <= $range; $i++) {
                $x = $attacker->getX() + ($dxdy['dx'] * $i);
                $y = $attacker->getY() + ($dxdy['dy'] * $i);

                $hit = $this->firstActorAt($actors, $attacker, $x, $y);
                if ($hit instanceof LocalActor) {
                    return [$hit];
                }
            }

            return [];
        }

        if ($aimMode === AimMode::Actor) {
            if ($targetX === null || $targetY === null) {
                return [];
            }
            if ($this->manhattan($attacker->getX(), $attacker->getY(), $targetX, $targetY) > $range) {
                return [];
            }

            $hit = $this->firstActorAt($actors, $attacker, $targetX, $targetY);
            return $hit instanceof LocalActor ? [$hit] : [];
        }

        if ($aimMode === AimMode::Point) {
            if ($targetX === null || $targetY === null) {
                return [];
            }
            if ($this->manhattan($attacker->getX(), $attacker->getY(), $targetX, $targetY) > $range) {
                return [];
            }

            $hit = $this->firstActorAt($actors, $attacker, $targetX, $targetY);
            return $hit instanceof LocalActor ? [$hit] : [];
        }

        return [];
    }

    /**
     * @param list<LocalActor> $actors
     *
     * @return list<LocalActor>
     */
    private function selectRay(
        LocalActor $attacker,
        array      $actors,
        AimMode    $aimMode,
        ?Direction $direction,
        int        $range,
        ?string    $piercing,
    ): array
    {
        if ($aimMode !== AimMode::Direction || $direction === null) {
            return [];
        }

        $piercing = strtolower((string)($piercing ?? 'first'));
        $dxdy     = $this->dirDelta($direction);

        $hits = [];
        for ($i = 1; $i <= $range; $i++) {
            $x = $attacker->getX() + ($dxdy['dx'] * $i);
            $y = $attacker->getY() + ($dxdy['dy'] * $i);

            $hit = $this->firstActorAt($actors, $attacker, $x, $y);
            if (!$hit instanceof LocalActor) {
                continue;
            }

            $hits[] = $hit;
            if ($piercing === 'first') {
                break;
            }
        }

        return $hits;
    }

    /**
     * @param list<LocalActor> $actors
     *
     * @return list<LocalActor>
     */
    private function selectAoe(
        LocalActor $attacker,
        array      $actors,
        AimMode    $aimMode,
        ?int       $targetX,
        ?int       $targetY,
        int        $range,
        ?int       $aoeRadius,
    ): array
    {
        $radius = max(0, (int)($aoeRadius ?? 0));

        $centerX = null;
        $centerY = null;

        if ($aimMode === AimMode::Self) {
            $centerX = $attacker->getX();
            $centerY = $attacker->getY();
        } elseif ($aimMode === AimMode::Point || $aimMode === AimMode::Actor) {
            if ($targetX === null || $targetY === null) {
                return [];
            }
            if ($this->manhattan($attacker->getX(), $attacker->getY(), $targetX, $targetY) > $range) {
                return [];
            }
            $centerX = $targetX;
            $centerY = $targetY;
        } else {
            return [];
        }

        $hits = [];
        foreach ($actors as $actor) {
            if ($actor === $attacker) {
                continue;
            }

            if ($this->manhattan($centerX, $centerY, $actor->getX(), $actor->getY()) <= $radius) {
                $hits[] = $actor;
            }
        }

        return $hits;
    }

    /**
     * @param list<LocalActor> $actors
     *
     * @return list<LocalActor>
     */
    private function selectPoint(
        LocalActor $attacker,
        array      $actors,
        AimMode    $aimMode,
        ?int       $targetX,
        ?int       $targetY,
        int        $range,
    ): array
    {
        if ($aimMode === AimMode::Actor) {
            if ($targetX === null || $targetY === null) {
                return [];
            }
            if ($this->manhattan($attacker->getX(), $attacker->getY(), $targetX, $targetY) > $range) {
                return [];
            }

            $hit = $this->firstActorAt($actors, $attacker, $targetX, $targetY);
            return $hit instanceof LocalActor ? [$hit] : [];
        }

        if ($aimMode === AimMode::Point) {
            if ($targetX === null || $targetY === null) {
                return [];
            }
            if ($this->manhattan($attacker->getX(), $attacker->getY(), $targetX, $targetY) > $range) {
                return [];
            }

            $hit = $this->firstActorAt($actors, $attacker, $targetX, $targetY);
            return $hit instanceof LocalActor ? [$hit] : [];
        }

        return [];
    }

    /**
     * @param list<LocalActor> $actors
     */
    private function firstActorAt(array $actors, LocalActor $attacker, int $x, int $y): ?LocalActor
    {
        foreach ($actors as $actor) {
            if ($actor === $attacker) {
                continue;
            }
            if ($actor->getX() === $x && $actor->getY() === $y) {
                return $actor;
            }
        }

        return null;
    }

    /**
     * @return array{dx:int,dy:int}
     */
    private function dirDelta(Direction $direction): array
    {
        return match ($direction) {
            Direction::North => ['dx' => 0, 'dy' => -1],
            Direction::South => ['dx' => 0, 'dy' => 1],
            Direction::East => ['dx' => 1, 'dy' => 0],
            Direction::West => ['dx' => -1, 'dy' => 0],
        };
    }

    private function manhattan(int $ax, int $ay, int $bx, int $by): int
    {
        return abs($ax - $bx) + abs($ay - $by);
    }
}
