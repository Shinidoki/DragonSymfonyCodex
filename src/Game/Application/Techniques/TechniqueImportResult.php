<?php

namespace App\Game\Application\Techniques;

final readonly class TechniqueImportResult
{
    public function __construct(
        public int $created,
        public int $updated,
        public int $skipped,
    )
    {
    }
}

