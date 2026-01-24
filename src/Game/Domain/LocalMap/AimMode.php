<?php

namespace App\Game\Domain\LocalMap;

enum AimMode: string
{
    case Self = 'self';
    case Actor = 'actor';
    case Direction = 'dir';
    case Point = 'point';
}

