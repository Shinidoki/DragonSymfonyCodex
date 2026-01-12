<?php

namespace App\Game\Domain\Stats;

enum Stat: string
{
    case Strength = 'strength';
    case Speed = 'speed';
    case Endurance = 'endurance';
    case Durability = 'durability';

    case KiCapacity = 'ki_capacity';
    case KiControl = 'ki_control';
    case KiRecovery = 'ki_recovery';

    case Focus = 'focus';
    case Discipline = 'discipline';
    case Adaptability = 'adaptability';
}

