<?php

namespace App\Game\Domain\Npc;

enum DailyActivity: string
{
    case Train = 'train';
    case Travel = 'travel';
    case Rest = 'rest';
}

