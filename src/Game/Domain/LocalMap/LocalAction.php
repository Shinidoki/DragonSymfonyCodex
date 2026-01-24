<?php

namespace App\Game\Domain\LocalMap;

use App\Game\Domain\Transformations\Transformation;

final readonly class LocalAction
{
    public AimMode $aimMode;

    public function __construct(
        public LocalActionType $type,
        public ?Direction      $direction = null,
        public ?int $targetActorId = null,
        public ?string $techniqueCode = null,
        public ?int            $targetX = null,
        public ?int            $targetY = null,
        public ?Transformation $transformation = null,
        ?AimMode               $aimMode = null,
    )
    {
        if ($type === LocalActionType::Move && $direction === null) {
            throw new \InvalidArgumentException('Move action requires a direction.');
        }

        if (($type === LocalActionType::Talk || $type === LocalActionType::Attack) && $targetActorId === null) {
            throw new \InvalidArgumentException('Talk/attack action requires a target actor id.');
        }

        if ($type === LocalActionType::Transform && $transformation === null) {
            throw new \InvalidArgumentException('Transform action requires a transformation.');
        }

        if ($type === LocalActionType::Technique) {
            if ($techniqueCode === null || trim($techniqueCode) === '') {
                throw new \InvalidArgumentException('Technique action requires a technique code.');
            }

            $hasTarget = $targetActorId !== null;
            $hasDir    = $direction !== null;
            $hasPoint  = $targetX !== null || $targetY !== null;

            if (($hasPoint && ($targetX === null || $targetY === null)) || ($targetX !== null && $targetX < 0) || ($targetY !== null && $targetY < 0)) {
                throw new \InvalidArgumentException('Technique point aim requires both targetX and targetY to be >= 0.');
            }

            $resolvedAimMode = $aimMode ?? AimMode::Self;
            if ($resolvedAimMode === AimMode::Self) {
                if ($hasTarget) {
                    $resolvedAimMode = AimMode::Actor;
                } elseif ($hasDir) {
                    $resolvedAimMode = AimMode::Direction;
                } elseif ($hasPoint) {
                    $resolvedAimMode = AimMode::Point;
                }
            }

            if (($resolvedAimMode === AimMode::Actor && !$hasTarget) || ($resolvedAimMode === AimMode::Direction && !$hasDir) || ($resolvedAimMode === AimMode::Point && !$hasPoint)) {
                throw new \InvalidArgumentException('Technique aimMode does not match provided aim parameters.');
            }

            $this->aimMode = $resolvedAimMode;
        } else {
            $this->aimMode = AimMode::Self;
        }

        if ($targetActorId !== null && $targetActorId <= 0) {
            throw new \InvalidArgumentException('targetActorId must be a positive integer.');
        }
    }
}
