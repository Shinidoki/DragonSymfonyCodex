<?php

namespace App\Game\Domain\LocalMap;

enum LocalActionType: string
{
    case Move = 'move';
    case Wait = 'wait';
    case Talk = 'talk';
    case Attack = 'attack';
    case Technique = 'technique';
}
