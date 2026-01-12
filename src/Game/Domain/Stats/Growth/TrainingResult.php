<?php

namespace App\Game\Domain\Stats\Growth;

use App\Game\Domain\Stats\CoreAttributes;

final readonly class TrainingResult
{
    /**
     * @param list<string> $messages
     */
    public function __construct(
        public CoreAttributes $before,
        public CoreAttributes $after,
        public array          $messages = [],
    )
    {
    }
}

