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

        $coord = new TileCoord(3, 7);

        $a = $g->biomeFor('seed-1', $coord);
        $b = $g->biomeFor('seed-1', $coord);
        $c = $g->biomeFor('seed-2', $coord);

        self::assertSame($a, $b);
        self::assertNotSame($a, $c);
        self::assertInstanceOf(Biome::class, $a);
    }

    public function testGeneratesDeterministicTileFeatures(): void
    {
        $g = new MapGenerator();

        $coord = new TileCoord(5, 5);
        $biome = $g->biomeFor('seed-1', $coord);

        $aSettlement = $g->hasSettlementFor('seed-1', $coord, $biome);
        $bSettlement = $g->hasSettlementFor('seed-1', $coord, $biome);
        self::assertSame($aSettlement, $bSettlement);

        $aDojo = $g->hasDojoFor('seed-1', $coord, $aSettlement);
        $bDojo = $g->hasDojoFor('seed-1', $coord, $aSettlement);
        self::assertSame($aDojo, $bDojo);
    }

    public function testOriginAlwaysHasSettlementAndDojo(): void
    {
        $g = new MapGenerator();

        $coord = new TileCoord(0, 0);
        $biome = $g->biomeFor('seed-1', $coord);

        self::assertTrue($g->hasSettlementFor('seed-1', $coord, $biome));
        self::assertTrue($g->hasDojoFor('seed-1', $coord, hasSettlement: true));
    }

    public function testRejectsEmptySeed(): void
    {
        $g = new MapGenerator();

        $this->expectException(\InvalidArgumentException::class);
        $g->biomeFor('  ', new TileCoord(0, 0));
    }
}
