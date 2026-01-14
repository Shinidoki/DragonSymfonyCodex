<?php

namespace App\Game\Domain\Techniques\Execution;

use App\Game\Domain\Techniques\TechniqueType;

final class BeamExecutor extends BlastExecutor
{
    public function supports(TechniqueType $type): bool
    {
        return $type === TechniqueType::Beam;
    }
}

