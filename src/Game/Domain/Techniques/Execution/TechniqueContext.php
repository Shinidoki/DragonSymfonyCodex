<?php

namespace App\Game\Domain\Techniques\Execution;

use App\Entity\Character;
use App\Entity\CharacterTechnique;
use App\Entity\LocalActor;
use App\Entity\TechniqueDefinition;

final readonly class TechniqueContext
{
    public function __construct(
        public int $sessionId,
        public int $tick,
        public TechniqueDefinition $definition,
        public CharacterTechnique $knowledge,
        public LocalActor $attackerActor,
        public LocalActor $defenderActor,
        public Character $attackerCharacter,
        public Character $defenderCharacter,
    )
    {
    }
}

