<?php

namespace App\Game\Domain\LocalMap;

enum LocalActionType: string
{
    case Move = 'move';
    case Wait = 'wait';
}

