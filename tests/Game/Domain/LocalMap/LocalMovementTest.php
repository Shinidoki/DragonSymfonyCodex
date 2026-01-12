<?php

namespace App\Tests\Game\Domain\LocalMap;

use App\Game\Domain\LocalMap\Direction;
use App\Game\Domain\LocalMap\LocalCoord;
use App\Game\Domain\LocalMap\LocalMapSize;
use App\Game\Domain\LocalMap\LocalMovement;
use PHPUnit\Framework\TestCase;

final class LocalMovementTest extends TestCase
{
    public function testMoveClampsToBounds(): void
    {
        $m    = new LocalMovement();
        $size = new LocalMapSize(3, 3);

        $next = $m->move(new LocalCoord(0, 0), Direction::West, $size);
        self::assertSame(0, $next->x);
        self::assertSame(0, $next->y);
    }
}

