<?php

namespace App\Tests\Game\Domain\Map\Travel;

use App\Game\Domain\Map\TileCoord;
use App\Game\Domain\Map\Travel\StepTowardTarget;
use PHPUnit\Framework\TestCase;

final class StepTowardTargetTest extends TestCase
{
    public function testStepsAlongXFirstDeterministically(): void
    {
        $stepper = new StepTowardTarget();

        $next = $stepper->step(new TileCoord(0, 0), new TileCoord(2, 0));

        self::assertSame(1, $next->x);
        self::assertSame(0, $next->y);
    }

    public function testStaysWhenAlreadyAtTarget(): void
    {
        $stepper = new StepTowardTarget();

        $current = new TileCoord(1, 1);
        $next    = $stepper->step($current, new TileCoord(1, 1));

        self::assertSame(1, $next->x);
        self::assertSame(1, $next->y);
    }
}

