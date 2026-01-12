<?php

namespace App\Game\Application\Simulation;

use App\Entity\Character;
use App\Entity\World;

/**
 * @param list<Character> $characters
 */
final readonly class AdvanceDayResult
{
    public function __construct(
        public World $world,
        public array $characters,
        public int   $daysAdvanced,
    )
    {
    }
}

