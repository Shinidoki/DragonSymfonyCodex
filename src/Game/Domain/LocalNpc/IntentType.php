<?php

namespace App\Game\Domain\LocalNpc;

enum IntentType: string
{
    case Idle = 'idle';
    case MoveTo = 'move_to';
    case TalkTo = 'talk_to';
    case Attack = 'attack';
}

