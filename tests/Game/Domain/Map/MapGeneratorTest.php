<?php

namespace App\Tests\Game\Domain\Map;

use App\Game\Domain\Map\Biome;
use App\Game\Domain\Map\MapGenerator;
use App\Game\Domain\Map\TileCoord;
use PHPUnit\Framework\TestCase;

final class MapGeneratorTest extends TestCase
{
    public function testIsDeterministicBySeedAndCoord(): void
    {
        $g = new MapGenerator();

        $a = $g->biomeFor('seed-1', new TileCoord(3, 7));
        $b = $g->biomeFor('seed-1', new TileCoord(3, 7));
        $c = $g->biomeFor('seed-2', new TileCoord(3, 7));

        self::assertSame($a, $b);
        self::assertNotSame($a, $c);
        self::assertInstanceOf(Biome::class, $a);
    }

    public function testRejectsEmptySeed(): void
    {
        $g = new MapGenerator();

        $this->expectException(\InvalidArgumentException::class);
        $g->biomeFor('  ', new TileCoord(0, 0));
    }
}

