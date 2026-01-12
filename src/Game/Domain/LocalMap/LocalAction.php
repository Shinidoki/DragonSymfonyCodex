<?php

namespace App\Game\Domain\LocalMap;

final readonly class LocalAction
{
    public function __construct(
        public LocalActionType $type,
        public ?Direction      $direction = null,
        public ?int $targetActorId = null,
    )
    {
        if ($type === LocalActionType::Move && $direction === null) {
            throw new \InvalidArgumentException('Move action requires a direction.');
        }

        if (($type === LocalActionType::Talk || $type === LocalActionType::Attack) && $targetActorId === null) {
            throw new \InvalidArgumentException('Talk/attack action requires a target actor id.');
        }

        if ($targetActorId !== null && $targetActorId <= 0) {
            throw new \InvalidArgumentException('targetActorId must be a positive integer.');
        }
    }
}
