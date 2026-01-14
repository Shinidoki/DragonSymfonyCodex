<?php

namespace App\Game\Domain\LocalMap;

final readonly class LocalAction
{
    public function __construct(
        public LocalActionType $type,
        public ?Direction      $direction = null,
        public ?int $targetActorId = null,
        public ?string $techniqueCode = null,
    )
    {
        if ($type === LocalActionType::Move && $direction === null) {
            throw new \InvalidArgumentException('Move action requires a direction.');
        }

        if (($type === LocalActionType::Talk || $type === LocalActionType::Attack) && $targetActorId === null) {
            throw new \InvalidArgumentException('Talk/attack action requires a target actor id.');
        }

        if ($type === LocalActionType::Technique && ($targetActorId === null || $techniqueCode === null || trim($techniqueCode) === '')) {
            throw new \InvalidArgumentException('Technique action requires a target actor id and technique code.');
        }

        if ($targetActorId !== null && $targetActorId <= 0) {
            throw new \InvalidArgumentException('targetActorId must be a positive integer.');
        }
    }
}
