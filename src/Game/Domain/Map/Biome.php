<?php

namespace App\Game\Domain\Map;

enum Biome: string
{
    case Plains = 'plains';
    case Forest = 'forest';
    case Mountains = 'mountains';
    case Ocean = 'ocean';
    case Desert = 'desert';
    case City = 'city';
}

