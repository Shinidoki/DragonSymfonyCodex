<?php

namespace App\Tests\Game\Domain\Npc;

use App\Entity\Character;
use App\Entity\World;
use App\Game\Domain\Npc\DailyActivity;
use App\Game\Domain\Npc\DailyPlanner;
use App\Game\Domain\Race;
use PHPUnit\Framework\TestCase;

final class DailyPlannerTest extends TestCase
{
    public function testPlansTravelWhenCharacterHasTravelTarget(): void
    {
        $world     = new World('seed-1');
        $character = new Character($world, 'Bulma', Race::Human);
        $character->setTravelTarget(10, 10);

        $planner = new DailyPlanner();

        self::assertSame(DailyActivity::Travel, $planner->planFor($character)->activity);
    }

    public function testDefaultsToTraining(): void
    {
        $world     = new World('seed-1');
        $character = new Character($world, 'Krillin', Race::Human);

        $planner = new DailyPlanner();

        self::assertSame(DailyActivity::Train, $planner->planFor($character)->activity);
    }
}

