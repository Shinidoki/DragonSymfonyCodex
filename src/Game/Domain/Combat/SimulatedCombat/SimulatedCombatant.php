<?php

namespace App\Game\Domain\Combat\SimulatedCombat;

use App\Entity\Character;
use App\Entity\CharacterTechnique;
use App\Entity\CharacterTransformation;

final readonly class SimulatedCombatant
{
    /**
     * @param list<CharacterTechnique>      $techniques
     * @param list<CharacterTransformation> $transformations
     */
    public function __construct(
        public Character $character,
        public int       $teamId,
        public array     $techniques = [],
        public array     $transformations = [],
    )
    {
        if ($teamId <= 0) {
            throw new \InvalidArgumentException('teamId must be positive.');
        }
    }
}
