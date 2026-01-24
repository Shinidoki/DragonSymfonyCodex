<?php

namespace App\Game\Domain\Npc;

enum NpcArchetype: string
{
    case Civilian = 'civilian';
    case Fighter = 'fighter';
    case Wanderer = 'wanderer';
}

