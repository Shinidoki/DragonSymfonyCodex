<?php

namespace App\Game\Domain\Techniques\Prepared;

enum PreparedTechniquePhase: string
{
    case Charging = 'charging';
    case Ready = 'ready';
}

