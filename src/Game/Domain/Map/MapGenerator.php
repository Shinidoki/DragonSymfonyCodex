<?php

namespace App\Game\Domain\Map;

final class MapGenerator
{
    public function biomeFor(string $seed, TileCoord $coord): Biome
    {
        $seed = trim($seed);
        if ($seed === '') {
            throw new \InvalidArgumentException('Seed must not be empty.');
        }

        $hash = hash('sha256', sprintf('%s:%d:%d', $seed, $coord->x, $coord->y));
        $n    = hexdec(substr($hash, 0, 8));

        $biomes = Biome::cases();
        $index  = $n % count($biomes);

        return $biomes[$index];
    }
}

