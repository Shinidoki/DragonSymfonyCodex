<?php

namespace App\Game\Domain\LocalMap;

enum Direction: string
{
    case North = 'north';
    case South = 'south';
    case East = 'east';
    case West = 'west';
}

