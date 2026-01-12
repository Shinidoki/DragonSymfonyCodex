<?php

namespace App\Game\Domain\Stats\Growth;

enum TrainingIntensity: string
{
    case Light = 'light';
    case Normal = 'normal';
    case Hard = 'hard';
    case Extreme = 'extreme';
}

