<?php

namespace App\Game\Domain\Techniques\Execution;

use App\Game\Domain\Techniques\TechniqueType;

interface TechniqueExecutor
{
    public function supports(TechniqueType $type): bool;

    public function execute(TechniqueContext $context): TechniqueExecution;
}

