<?php

namespace App\Tests\Game\Domain\Map;

use App\Game\Domain\Map\TileCoord;
use PHPUnit\Framework\TestCase;

final class TileCoordTest extends TestCase
{
    public function testRejectsNegativeCoordinates(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new TileCoord(-1, 0);
    }
}

