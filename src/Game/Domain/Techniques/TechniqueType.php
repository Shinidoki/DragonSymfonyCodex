<?php

namespace App\Game\Domain\Techniques;

enum TechniqueType: string
{
    case Blast = 'blast';
    case Beam = 'beam';
    case Charged = 'charged';
}

