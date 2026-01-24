<?php

namespace App\Game\Application\World;

use App\Entity\World;

/**
 * @param array<string,int> $createdByArchetype
 */
final readonly class PopulateWorldResult
{
    /**
     * @param array<string,int> $createdByArchetype
     */
    public function __construct(
        public World $world,
        public int   $created,
        public array $createdByArchetype,
    )
    {
    }
}

