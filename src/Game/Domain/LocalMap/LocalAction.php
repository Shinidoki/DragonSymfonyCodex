<?php

namespace App\Game\Domain\LocalMap;

final readonly class LocalAction
{
    public function __construct(
        public LocalActionType $type,
        public ?Direction      $direction = null,
    )
    {
        if ($type === LocalActionType::Move && $direction === null) {
            throw new \InvalidArgumentException('Move action requires a direction.');
        }
    }
}

