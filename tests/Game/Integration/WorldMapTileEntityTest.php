<?php

namespace App\Tests\Game\Integration;

use App\Entity\World;
use App\Entity\WorldMapTile;
use App\Game\Domain\Map\Biome;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class WorldMapTileEntityTest extends KernelTestCase
{
    public function testWorldMapTileEntityIsLoadable(): void
    {
        self::bootKernel();

        $world = new World('seed-1');
        $tile  = new WorldMapTile($world, 0, 0, Biome::Plains);

        self::assertSame(0, $tile->getX());
        self::assertSame(Biome::Plains, $tile->getBiome());
    }
}

